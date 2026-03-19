<template>
  <k-section :headline="headline" :buttons="sectionButtons" :required="Boolean(min)" :invalid="isInvalid">
    <k-dropdown-content ref="options" :options="dropdownOptions" align-x="end" />
    <k-loader v-if="isLoading" />
    <k-empty v-else-if="!modules.length" icon="box" @click="add()">
      {{ empty }}
    </k-empty>
    <k-draggable v-else :list="modules" :options="dragOptions" @sort="onSort" class="k-modules-list">
      <k-module-card v-for="module in modules" :key="module.id" :module="module"
        :expanded="expanded[module.id] === true" :loading="!!loadingModules[module.id]"
        :selected="selectedModule === module.id" :values="currentValues(module.id)" :page-url="pageUrl(module.id)"
        :has-error="!!(fieldData[module.id] && fieldData[module.id].error)" @toggle="toggle(module)"
        @toggle-visibility="toggleVisibility(module)" @select="select(module)" @input="onInput(module, $event)"
        @add="addAt(module, $event)" @remove="remove(module)" @duplicate="duplicate(module)"
        @change-type="changeType(module)" @change-slug="changeSlug(module)" @sort="sortModule(module, $event)" />
    </k-draggable>
    <footer v-if="!isLoading && modules.length && canAdd">
      <k-button icon="add" size="xs" variant="filled" @click="add()" />
    </footer>
  </k-section>
</template>

<script>
import ModuleCard from "./ModuleCard.vue";

export default {
  components: {
    "k-module-card": ModuleCard,
  },

  // ---------------------------------------------------------------
  // Props from Kirby's section system
  // ---------------------------------------------------------------

  props: {
    name: String,
    parent: String,
    timestamp: Number,
  },

  // ---------------------------------------------------------------
  // State
  // ---------------------------------------------------------------

  data() {
    return {
      // From server (toArray response)
      headline: "",
      modules: [],
      empty: "",
      link: null,
      canAdd: true,
      min: null,
      max: null,

      // Client-side tracking maps (keyed by module ID)
      expanded: {},
      fieldData: {},
      changes: {},
      serverPendingIds: [],
      loadingModules: {},

      // UI state
      isLoading: true,
      selectedModule: null,
      pendingInsertPosition: null,
      dragOptions: { handle: ".k-sort-handle" },
    };
  },

  // ---------------------------------------------------------------
  // Computed
  // ---------------------------------------------------------------

  computed: {
    sectionUrl() {
      return this.parent + "/sections/" + this.name;
    },
    isInvalid() {
      if (this.min && this.modules.length < this.min) return true;
      if (this.max && this.modules.length > this.max) return true;
      return false;
    },
    sectionButtons() {
      const buttons = [
        { icon: "cog", click: () => this.$refs.options?.toggle() },
      ];
      if (this.canAdd) {
        buttons.push({ icon: "add", text: this.$t("add"), click: () => this.add(), responsive: true });
      }
      return buttons;
    },
    dropdownOptions() {
      return [
        {
          text: this.$t("modules.expandAll"),
          icon: "expand",
          click: () => this.expandAll(),
          disabled: this.isFullyExpanded,
        },
        {
          text: this.$t("modules.collapseAll"),
          icon: "collapse",
          click: () => this.collapseAll(),
          disabled: this.isFullyCollapsed,
        },
        "-",
        {
          text: this.$t("delete.all"),
          icon: "trash",
          click: () => this.$panel.dialog.open({
            component: "k-remove-dialog",
            props: { text: this.$t("modules.deleteAll.confirm") },
            on: {
              submit: async () => {
                await this.$api.post(this.sectionUrl + "/deleteAll");
                this.$panel.dialog.close();
                this.fetch();
              },
            },
          }),
          disabled: !this.modules.length || !this.link,
        },
      ];
    },
    isFullyExpanded() {
      return this.modules.length > 0 && this.modules.every((m) => this.expanded[m.id]);
    },
    isFullyCollapsed() {
      return this.modules.length > 0 && this.modules.every((m) => !this.expanded[m.id]);
    },
  },

  // ---------------------------------------------------------------
  // Lifecycle
  // ---------------------------------------------------------------

  watch: {
    timestamp() {
      this.fetch();
    },
  },
  created() {
    // Track content language for cache invalidation on language switch
    this._language = this.$panel.language?.code;

    // Ensure the container page exists (section name = container slug)
    this.$api.post(this.sectionUrl + "/create-container").then(() => this.fetch());

    // Bridge module changes into the parent page's publish/discard flow.
    // The { api } guard ensures only this section's parent triggers it.
    this._onPublish = ({ api }) => {
      if (this.isParentApi(api)) this.applyChanges("publish");
    };
    this._onDiscard = ({ api }) => {
      if (this.isParentApi(api)) this.applyChanges("discard");
    };
    this.$events.on("content.publish", this._onPublish);
    this.$events.on("content.discard", this._onDiscard);
  },
  mounted() {
    document.addEventListener("mousedown", this.onClickOutside);
  },
  destroyed() {
    this.$events.off("content.publish", this._onPublish);
    this.$events.off("content.discard", this._onDiscard);
    document.removeEventListener("mousedown", this.onClickOutside);
  },

  // ---------------------------------------------------------------
  // Methods
  // ---------------------------------------------------------------

  methods: {

    // --- Data fetching ---------------------------------------------------

    async fetch() {
      try {
        // Clear field cache when content language changes
        const lang = this.$panel.language?.code;
        if (this._language !== undefined && this._language !== lang) {
          this.fieldData = {};
          this.changes = {};
        }
        this._language = lang;

        // Snapshot current state for change detection after refresh
        const previousIds = new Set(this.modules.map((m) => m.id));
        const previousTemplates = new Map(this.modules.map((m) => [m.id, m.template]));

        const response = await this.$api.get(this.sectionUrl);
        this.headline = response.options.headline;
        this.modules = response.data;
        this.empty = response.options.empty;
        this.link = response.options.link;
        this.canAdd = response.options.add;
        this.min = response.options.min;
        this.max = response.options.max;

        // Discard stale data when a module's type was changed
        for (const module of this.modules) {
          const prev = previousTemplates.get(module.id);
          if (prev && prev !== module.template) {
            this.$delete(this.fieldData, module.id);
            this.$delete(this.changes, module.id);
            module.hasPendingChanges = false;
            this.$api.post(this.pageUrl(module.id) + "/changes/discard", null, { silent: true }).catch(() => { });
          }
        }

        const collapsed = this.loadCollapsedState();
        for (const module of this.modules) {
          this.restoreExpandState(module, collapsed);
        }

        this.reconcileState();
        this.positionNewModule(previousIds);
      } catch (e) {
        this.handleError(e);
      } finally {
        this.isLoading = false;
      }
    },

    restoreExpandState(module, collapsed) {
      if (collapsed.includes(module.id)) {
        this.$set(this.expanded, module.id, false);
        return;
      }

      // Fields must load before k-sections can render them
      const needsLoad = module.hasFields &&
        (!this.fieldData[module.id] || module.hasPendingChanges);
      if (needsLoad) {
        this.loadFields(module).then(() => {
          this.$nextTick(() => {
            this.$set(this.expanded, module.id, true);
          });
        });
      } else {
        this.$set(this.expanded, module.id, true);
      }
    },

    // Prune all tracking maps against current modules and derive dirty state.
    // Handles deleted modules, type changes, and orphaned server-side _changes.
    reconcileState() {
      const currentIds = new Set(this.modules.map((m) => m.id));

      for (const id of Object.keys(this.changes)) {
        if (!currentIds.has(id)) this.$delete(this.changes, id);
      }
      for (const id of Object.keys(this.fieldData)) {
        if (!currentIds.has(id)) this.$delete(this.fieldData, id);
      }
      for (const id of Object.keys(this.expanded)) {
        if (!currentIds.has(id)) this.$delete(this.expanded, id);
      }
      for (const id of Object.keys(this.loadingModules)) {
        if (!currentIds.has(id)) this.$delete(this.loadingModules, id);
      }

      // Server-side _changes not tracked locally (e.g. from a previous session)
      this.serverPendingIds = this.modules
        .filter((m) => m.hasPendingChanges && !this.changes[m.id])
        .map((m) => m.id);
      const hasLocalChanges = Object.keys(this.changes).length > 0;

      if (this.serverPendingIds.length > 0 || hasLocalChanges) {
        this.dirtyParent();
      } else {
        this.undirtyParent();
      }
    },

    // Insert newly created module at the requested position and focus it
    positionNewModule(previousIds) {
      if (this.pendingInsertPosition == null) return;

      const newModule = this.modules.find((m) => !previousIds.has(m.id));
      if (newModule) {
        const ids = this.modules
          .map((m) => m.id)
          .filter((id) => id !== newModule.id);
        if (this.pendingInsertPosition >= 0) {
          ids.splice(this.pendingInsertPosition, 0, newModule.id);
        } else {
          ids.push(newModule.id);
        }
        this.modules = ids.map((id) =>
          this.modules.find((m) => m.id === id),
        );
        this.onSort();
        this.$nextTick(() => {
          const el = this.$el.querySelector(`[data-module-id="${newModule.id}"]`);
          if (el) el.focus();
        });
      }
      this.pendingInsertPosition = null;
    },

    // Load form fields + values for inline editing
    async loadFields(module) {
      try {
        const response = await this.$api.get(
          this.sectionUrl + "/fields/" + this.encodeId(module.id),
        );
        this.$set(this.fieldData, module.id, {
          values: response.values,
          original: JSON.stringify(response.values), // Snapshot for revert detection
        });
      } catch (e) {
        this.handleError(e);
        this.$set(this.fieldData, module.id, { error: true });
      }
    },

    // --- Module actions --------------------------------------------------

    add(position = -1) {
      if (!this.canAdd) return;
      this.pendingInsertPosition = position;
      this.$dialog("modules/create", {
        query: {
          parent: this.link || this.parent,
          view: this.parent,
          section: this.name,
        },
      });
    },
    addAt(module, offset) {
      const index = this.modules.findIndex((m) => m.id === module.id);
      this.add(index + offset);
    },
    async duplicate(module) {
      try {
        await this.$api.post(
          this.sectionUrl + "/duplicate/" + this.encodeId(module.id),
        );
        this.pendingInsertPosition = -1;
        this.fetch();
      } catch (e) {
        this.handleError(e);
      }
    },
    changeType(module) {
      this.$dialog("modules/change-type", {
        query: { page: this.encodeId(module.id) },
      });
    },
    changeSlug(module) {
      this.$dialog("modules/change-slug", {
        query: { page: this.encodeId(module.id) },
      });
    },

    // Keyboard sorting (ArrowUp/ArrowDown on the sort handle)
    async sortModule(module, direction) {
      const index = this.modules.findIndex((m) => m.id === module.id);
      const target = index + direction;
      if (target < 0 || target >= this.modules.length) return;

      const clone = [...this.modules];
      clone.splice(index, 1);
      clone.splice(target, 0, module);
      this.modules = clone;
      this.onSort();

      await this.$nextTick();
      const el = this.$el.querySelector(`[data-module-id="${module.id}"] .k-sort-handle`);
      if (el) el.focus();
    },

    // Persist sort order to server
    async onSort() {
      const ids = this.modules.map((m) => m.id);
      try {
        await this.$api.post(this.sectionUrl + "/sort", { ids });
      } catch (e) {
        this.handleError(e);
      }
      await this.fetch();
    },

    async toggleVisibility(module) {
      const ids = this.modules.map((m) => m.id);
      try {
        await this.$api.post(
          this.sectionUrl + "/toggle-visibility/" + this.encodeId(module.id),
        );
        // Re-send sort order — Kirby re-sorts by status after visibility change
        await this.$api.post(this.sectionUrl + "/sort", { ids });
        await this.fetch();
        this.$nextTick(() => {
          const el = this.$el.querySelector(`[data-module-id="${module.id}"]`);
          if (el) el.scrollIntoView({ block: "nearest" });
        });
      } catch (e) {
        this.handleError(e);
      }
    },

    remove(module) {
      this.$dialog(this.pageUrl(module.id) + "/delete", {
        query: {
          redirect: this.parent,
        },
      });
    },

    // --- Field change tracking -------------------------------------------
    // Modules are child pages with their own content files.
    // Changes are saved to each module's _changes version immediately,
    // then published/discarded when the parent page is saved/discarded.

    async onInput(module, values) {
      const data = this.fieldData[module.id];
      const unchanged = data?.original && JSON.stringify(values) === data.original;

      if (unchanged) {
        // User reverted to original — discard the server draft
        this.$delete(this.changes, module.id);
        this.$api.post(this.pageUrl(module.id) + "/changes/discard", null, { silent: true }).catch(() => { });
      } else {
        this.$set(this.changes, module.id, values);
        try {
          await this.$api.post(this.pageUrl(module.id) + "/changes/save", values, { silent: true });
        } catch (e) {
          this.handleError(e);
        }
      }

      const hasLocalChanges = Object.keys(this.changes).length > 0;
      if (this.serverPendingIds.length > 0 || hasLocalChanges) {
        this.dirtyParent();
      } else {
        this.undirtyParent();
      }
    },

    // Publish or discard all pending module changes (triggered by parent Save/Discard)
    async applyChanges(action) {
      if (this._isApplying) return;
      this._isApplying = true;

      try {
        const currentIds = new Set(this.modules.map((m) => m.id));
        const changedIds = Object.keys(this.changes);
        const allIds = [...new Set([...changedIds, ...this.serverPendingIds])]
          .filter((id) => currentIds.has(id));

        if (!allIds.length) {
          this.changes = {};
          this.serverPendingIds = [];
          this.undirtyParent();
          return;
        }

        await Promise.all(
          allIds.map((moduleId) =>
            this.$api
              .post(this.pageUrl(moduleId) + "/changes/" + action)
              .catch(() => { }),
          ),
        );

        this.changes = {};
        this.serverPendingIds = [];

        // Reload fields for expanded modules to reflect the new state
        await Promise.all(
          this.modules
            .filter((m) => allIds.includes(m.id) && this.expanded[m.id] && m.hasFields)
            .map((m) => this.loadFields(m)),
        );
      } finally {
        this._isApplying = false;
      }
    },

    currentValues(moduleId) {
      return this.changes[moduleId]
        || this.fieldData[moduleId]?.values
        || {};
    },

    // Dirty/undirty the parent page's Save/Discard buttons.
    // Key is scoped per section name to support multiple modules sections.
    dirtyParent() {
      this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: String(Date.now()) });
    },
    undirtyParent() {
      this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: undefined });
    },

    // --- Expand/collapse -------------------------------------------------
    // Persisted to localStorage per page + section name.

    async toggle(module) {
      if (this.expanded[module.id]) {
        this.$set(this.expanded, module.id, false);
        this.saveCollapsedState();
        return;
      }
      if (module.hasFields && !this.fieldData[module.id]) {
        this.$set(this.loadingModules, module.id, true);
        await this.loadFields(module);
        this.$delete(this.loadingModules, module.id);
        await this.$nextTick();
      }
      this.$set(this.expanded, module.id, true);
      this.saveCollapsedState();
    },
    saveCollapsedState() {
      const key = "kirby-modules:" + this.parent + ":" + this.name;
      const ids = Object.keys(this.expanded).filter(
        (id) => !this.expanded[id],
      );
      localStorage.setItem(key, JSON.stringify(ids));
    },
    loadCollapsedState() {
      const key = "kirby-modules:" + this.parent + ":" + this.name;
      try {
        const stored = localStorage.getItem(key);
        return stored ? JSON.parse(stored) : [];
      } catch (e) {
        return [];
      }
    },
    async expandAll() {
      const toLoad = this.modules.filter(
        (m) => !this.expanded[m.id] && m.hasFields && !this.fieldData[m.id],
      );
      for (const m of toLoad) {
        this.$set(this.loadingModules, m.id, true);
      }
      await Promise.all(toLoad.map((m) => this.loadFields(m)));
      for (const m of toLoad) {
        this.$delete(this.loadingModules, m.id);
      }
      for (const m of this.modules) {
        this.$set(this.expanded, m.id, true);
      }
      this.saveCollapsedState();
    },
    collapseAll() {
      for (const m of this.modules) {
        this.$set(this.expanded, m.id, false);
      }
      this.saveCollapsedState();
    },

    // --- UI helpers -------------------------------------------------------

    select(module) {
      this.selectedModule = module.id;
    },
    onClickOutside(e) {
      const clickedModule = e.target.closest(".k-module");
      if (clickedModule && this.$el.contains(clickedModule)) return;
      if (e.target.closest(".k-dialog, .k-drawer")) return;
      this.selectedModule = null;
    },

    // --- Utilities --------------------------------------------------------

    // SiteView passes parent="site" (no slash) but content events use "/site"
    isParentApi(api) {
      return api?.replace(/^\//, "") === this.parent.replace(/^\//, "");
    },
    handleError(e) {
      this.$panel.notification.error(e.message || this.$t("error"));
    },
    encodeId(id) {
      return id.replace(/\//g, "+");
    },
    pageUrl(moduleId) {
      return "pages/" + this.encodeId(moduleId);
    },
  },
};
</script>

<style scoped>
.k-module+.k-module {
  margin-block-start: var(--spacing-2);
}

footer {
  display: flex;
  justify-content: center;
  margin-block-start: var(--spacing-3);
}
</style>
