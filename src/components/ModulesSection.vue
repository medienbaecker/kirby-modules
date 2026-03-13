<template>
  <k-section :headline="headline" :buttons="sectionButtons">
    <k-dropdown-content ref="options" :options="dropdownOptions" align-x="end" />
    <k-loader v-if="isLoading" />
    <k-empty v-else-if="!modules.length" icon="box" @click="add()">
      {{ empty }}
    </k-empty>
    <k-draggable v-else :list="modules" :options="dragOptions" @sort="onSort" class="k-modules-list">
      <k-module-card
        v-for="module in modules"
        :key="module.id"
        :module="module"
        :expanded="expanded[module.id] === true"
        :loading="!!loadingModules[module.id]"
        :selected="selectedModule === module.id"
        :values="currentValues(module.id)"
        :page-url="pageUrl(module.id)"
        :has-error="!!(fieldData[module.id] && fieldData[module.id].error)"
        @toggle="toggle(module)"
        @toggle-visibility="toggleVisibility(module)"
        @select="select(module)"
        @input="onInput(module, $event)"
        @add="addAt(module, $event)"
        @remove="remove(module)"
        @duplicate="duplicate(module)"
        @change-type="changeType(module)"
        @sort="sortModule(module, $event)"
      />
    </k-draggable>
    <footer v-if="!isLoading && modules.length">
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
  props: {
    name: String,
    parent: String,
    timestamp: Number,
  },
  data() {
    return {
      headline: "",
      modules: [],
      empty: "",
      link: null,
      expanded: {},
      fieldData: {},
      changes: {},
      isLoading: true,
      loadingModules: {},
      selectedModule: null,
      pendingInsertPosition: null,
      dragOptions: { handle: ".k-sort-handle" },
    };
  },
  computed: {
    sectionUrl() {
      return this.parent + "/sections/" + this.name;
    },
    sectionButtons() {
      return [
        { icon: "cog", click: () => this.$refs.options?.toggle() },
        { icon: "add", text: this.$t("add"), click: () => this.add(), responsive: true },
      ];
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
  watch: {
    timestamp() {
      this.fetch();
    },
  },
  created() {
    const init = this.parent !== "site"
      ? this.$api.post(this.parent + "/modules")
      : Promise.resolve();
    init.then(() => this.fetch());

    // Bridge module changes into the parent page's publish/discard flow
    this._onPublish = () => this.applyChanges("publish");
    this._onDiscard = () => this.applyChanges("discard");
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
  methods: {
    // --- Data fetching ---
    async fetch() {
      try {
        const previousIds = new Set(this.modules.map((m) => m.id));
        const previousTemplates = new Map(this.modules.map((m) => [m.id, m.template]));
        const response = await this.$api.get(this.sectionUrl);
        this.headline = response.headline;
        this.modules = response.modules;
        this.empty = response.empty;
        this.link = response.link;

        // Invalidate cached fields when template changed (e.g. after "change type")
        for (const module of this.modules) {
          const prev = previousTemplates.get(module.id);
          if (prev && prev !== module.template) {
            this.$delete(this.fieldData, module.id);
          }
        }

        const collapsed = this.loadCollapsedState();
        for (const module of this.modules) {
          this.restoreExpandState(module, collapsed);
        }

        this.syncPendingChanges();
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
    // Modules are child pages — they can have server-side pending changes
    // independent of the parent. Dirty the parent so Save/Discard buttons appear.
    syncPendingChanges() {
      const hasPending = this.modules.some((m) => m.hasPendingChanges);
      if (hasPending) {
        this.dirtyParent();
      }
    },
    // After create dialog, position the new module at the requested index
    positionNewModule(previousIds) {
      if (this.pendingInsertPosition == null) return;

      const newModule = this.modules.find((m) => !previousIds.has(m.id));
      if (newModule) {
        if (this.pendingInsertPosition >= 0) {
          const ids = this.modules
            .map((m) => m.id)
            .filter((id) => id !== newModule.id);
          ids.splice(this.pendingInsertPosition, 0, newModule.id);
          this.modules = ids.map((id) =>
            this.modules.find((m) => m.id === id),
          );
          this.onSort();
        }
        this.$nextTick(() => {
          const el = this.$el.querySelector(`[data-module-id="${newModule.id}"]`);
          if (el) el.focus();
        });
      }
      this.pendingInsertPosition = null;
    },
    async loadFields(module) {
      try {
        const response = await this.$api.get(
          this.sectionUrl + "/fields/" + this.encodeId(module.id),
        );
        this.$set(this.fieldData, module.id, {
          values: response.values,
          original: JSON.stringify(response.values),
        });
      } catch (e) {
        this.handleError(e);
        this.$set(this.fieldData, module.id, { error: true });
      }
    },

    // --- Module actions ---
    add(position = -1) {
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
        // -1 = append to end, but still triggers focus via positionNewModule
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
    async onSort() {
      const ids = this.modules.map((m) => m.id);
      try {
        await this.$api.post(this.sectionUrl + "/sort", { ids });
      } catch (e) {
        this.handleError(e);
      }
      this.fetch();
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

    // --- Field change tracking ---
    // Each module is a separate Kirby page with its own content file.
    // Changes are tracked per-module and bridged to the parent's Save/Discard.
    async onInput(module, values) {
      const data = this.fieldData[module.id];
      // Compare against original snapshot to detect manual reverts
      const unchanged = data?.original && JSON.stringify(values) === data.original;

      if (unchanged) {
        this.$delete(this.changes, module.id);
        this.$api.post(this.pageUrl(module.id) + "/changes/discard").catch(() => { });
      } else {
        this.$set(this.changes, module.id, values);
        try {
          await this.$api.post(this.pageUrl(module.id) + "/changes/save", values);
        } catch (e) {
          this.handleError(e);
        }
      }

      if (Object.keys(this.changes).length > 0) {
        this.dirtyParent();
      } else {
        this.undirtyParent();
      }
    },
    async applyChanges(action) {
      const changedIds = Object.keys(this.changes);
      if (!changedIds.length) return;

      await Promise.all(
        changedIds.map((moduleId) =>
          this.$api
            .post(this.pageUrl(moduleId) + "/changes/" + action)
            .catch((e) => this.handleError(e)),
        ),
      );

      this.changes = {};
      await Promise.all(
        this.modules
          .filter((m) => changedIds.includes(m.id) && this.expanded[m.id] && m.hasFields)
          .map((m) => this.loadFields(m)),
      );
    },
    currentValues(moduleId) {
      return this.changes[moduleId]
        || this.fieldData[moduleId]?.values
        || {};
    },
    // Timestamp string triggers Panel's dirty detection via content diff
    dirtyParent() {
      this.$panel.content.merge({ _modulesChanged: String(Date.now()) });
    },
    // Setting undefined drops the key from Panel's diff, clearing dirty state
    undirtyParent() {
      this.$panel.content.merge({ _modulesChanged: undefined });
    },

    // --- Expand/collapse state ---
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
      const key = "kirby-modules:" + this.parent;
      const ids = Object.keys(this.expanded).filter(
        (id) => !this.expanded[id],
      );
      localStorage.setItem(key, JSON.stringify(ids));
    },
    loadCollapsedState() {
      const key = "kirby-modules:" + this.parent;
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

    // --- UI helpers ---
    select(module) {
      this.selectedModule = module.id;
    },
    onClickOutside(e) {
      if (e.target.closest(".k-module, .k-dialog, .k-drawer")) return;
      this.selectedModule = null;
    },

    // --- Utilities ---
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

<style>
.k-modules-list>.k-module+.k-module {
  margin-top: var(--spacing-2);
}

.k-section-name-modules footer {
  display: flex;
  justify-content: center;
  margin-top: var(--spacing-3);
}

.k-topbar-breadcrumb li:has(a[href$="+modules"]) {
  display: none;
}
</style>
