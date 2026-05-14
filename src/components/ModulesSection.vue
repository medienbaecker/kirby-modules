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
      <k-button icon="add" size="xs" variant="filled" :title="$t('add')" @click="add()" />
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
      canAdd: true,
      min: null,
      max: null,

      expanded: {},
      fieldData: {},
      changes: {},
      serverPendingIds: [],
      loadingModules: {},

      isLoading: true,
      selectedModule: null,
      pendingInsertPosition: null,
      pendingFocusInput: false,
      dragOptions: { handle: ".k-sort-handle" },
    };
  },

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
        { icon: "cog", title: this.$t("options"), click: () => this.$refs.options?.toggle() },
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

  watch: {
    timestamp() {
      this.fetch();
    },
  },
  created() {
    // Captured for cache invalidation on language switch in fetch().
    this._language = this.$panel.language?.code;

    // Section name = container slug; create it on first mount if missing.
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

  methods: {

    async fetch() {
      try {
        const lang = this.$panel.language?.code;
        if (this._language !== undefined && this._language !== lang) {
          this.fieldData = {};
          this.changes = {};
        }
        this._language = lang;

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
        .filter((m) => m.hasPendingChanges && !this.changes[m.id] && !m.lock?.isLocked)
        .map((m) => m.id);
      const hasLocalChanges = Object.keys(this.changes).length > 0;

      if (this.serverPendingIds.length > 0 || hasLocalChanges) {
        this.dirtyParent();
      } else {
        this.undirtyParent();
      }
    },

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

          if (this.pendingFocusInput && el) {
            this.pendingFocusInput = false;
            // Mirror Kirby's internal drawer/dialog focus priority:
            // explicit [autofocus] first, then first form control.
            const selectors = [
              ".k-module-content :where([autofocus], [data-autofocus])",
              ".k-module-content :where(input:not([type=hidden]), textarea, select, [contenteditable=true], .input-focus)",
            ];
            const deadline = Date.now() + 1000;
            const tryFocus = () => {
              for (const selector of selectors) {
                const input = el.querySelector(selector);
                if (input) { input.focus(); return; }
              }
              if (Date.now() < deadline) setTimeout(tryFocus, 50);
            };
            tryFocus();
          }
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

    add(position = -1) {
      if (!this.canAdd) return;
      this.pendingInsertPosition = position;
      this.pendingFocusInput = true;
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
        // Clear any stale focus intent from a previously cancelled Add.
        this.pendingFocusInput = false;
        // Flush any pending client-side edits so they land in the server's
        // _changes/ before the duplicate endpoint copies that directory.
        // Without this, a fast "type then duplicate" produces an empty copy.
        const pending = this.changes[module.id];
        if (pending) {
          await this.$api.post(
            this.pageUrl(module.id) + "/changes/save",
            pending,
            { silent: true },
          );
        }
        await this.$api.post(
          this.sectionUrl + "/duplicate/" + this.encodeId(module.id),
        );
        const index = this.modules.findIndex((m) => m.id === module.id);
        this.pendingInsertPosition = index >= 0 ? index + 1 : -1;
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
      await this.fetch();
    },

    async toggleVisibility(module) {
      try {
        await this.$api.post(
          this.sectionUrl + "/toggle-visibility/" + this.encodeId(module.id),
        );
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

    // Module changes are saved to each module's _changes version immediately,
    // then published/discarded when the parent page is saved/discarded.
    async onInput(module, values) {
      const data = this.fieldData[module.id];
      const unchanged = data?.original && JSON.stringify(values) === data.original;

      if (unchanged) {
        this.$delete(this.changes, module.id);
        this.$api.post(this.pageUrl(module.id) + "/changes/discard", null, { silent: true }).catch(() => { });
      } else {
        try {
          await this.$api.post(this.pageUrl(module.id) + "/changes/save", values, { silent: true });
          // Commit local state only after the server accepts; otherwise a
          // 423 leaves the parent dirty with nothing the user can publish.
          this.$set(this.changes, module.id, values);
        } catch (e) {
          this.handleError(e);
          return;
        }
      }

      const hasLocalChanges = Object.keys(this.changes).length > 0;
      if (this.serverPendingIds.length > 0 || hasLocalChanges) {
        this.dirtyParent();
      } else {
        this.undirtyParent();
      }
    },

    // Triggered by parent page Save/Discard.
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

        const results = await Promise.allSettled(
          allIds.map((moduleId) =>
            this.$api.post(this.pageUrl(moduleId) + "/changes/" + action),
          ),
        );

        const succeeded = [];
        const lockFailed = [];
        const otherFailed = [];
        results.forEach((result, i) => {
          const id = allIds[i];
          if (result.status === "fulfilled") succeeded.push(id);
          else if (result.reason?.details?.isLocked) lockFailed.push(id);
          else otherFailed.push({ id, reason: result.reason });
        });

        for (const id of succeeded) this.$delete(this.changes, id);
        this.serverPendingIds = this.serverPendingIds.filter(
          (id) => !succeeded.includes(id),
        );

        if (lockFailed.length > 0) {
          this.$panel.notification.error(this.$t("modules.lock.applyFailed"));
        }
        for (const { id, reason } of otherFailed) {
          const name = this.modules.find((m) => m.id === id)?.moduleName || id;
          this.$panel.notification.error({
            message: `${name}: ${reason?.message || this.$t("error")}`,
            details: reason?.details,
          });
        }

        if (lockFailed.length > 0 || otherFailed.length > 0) {
          await this.fetch();
          const firstFailed = lockFailed[0] || otherFailed[0]?.id;
          if (firstFailed) this.revealModule(firstFailed);
        } else {
          await Promise.all(
            this.modules
              .filter((m) => succeeded.includes(m.id) && this.expanded[m.id] && m.hasFields)
              .map((m) => this.loadFields(m)),
          );

          if (Object.keys(this.changes).length === 0 && this.serverPendingIds.length === 0) {
            this.undirtyParent();
          }
        }
      } finally {
        this._isApplying = false;
      }
    },

    currentValues(moduleId) {
      return this.changes[moduleId]
        || this.fieldData[moduleId]?.values
        || {};
    },

    // Section-scoped key — multiple modules sections on one page dirty independently.
    dirtyParent() {
      this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: String(Date.now()) });
    },
    undirtyParent() {
      this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: undefined });
    },

    // Collapsed state persists in localStorage per page + section name.
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

    select(module) {
      this.selectedModule = module.id;
    },
    async revealModule(id) {
      const module = this.modules.find((m) => m.id === id);
      if (!module) return;
      if (!this.expanded[id]) await this.toggle(module);
      await this.$nextTick();
      const el = this.$el.querySelector(`[data-module-id="${id}"]`);
      el?.scrollIntoView({ block: "nearest", behavior: "smooth" });
      el?.focus();
    },
    onClickOutside(e) {
      const clickedModule = e.target.closest(".k-module");
      if (clickedModule && this.$el.contains(clickedModule)) return;
      if (e.target.closest(".k-dialog, .k-drawer")) return;
      this.selectedModule = null;
    },

    // SiteView passes parent="site" (no slash) but content events use "/site"
    isParentApi(api) {
      return api?.replace(/^\//, "") === this.parent.replace(/^\//, "");
    },
    handleError(e) {
      if (e?.details?.isLocked) {
        this.fetch();
        return;
      }
      this.$panel.notification.error(e?.message || this.$t("error"));
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
