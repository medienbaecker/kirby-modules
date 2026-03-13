<template>
  <k-section :headline="headline" :buttons="sectionButtons">
    <k-dropdown-content ref="options" :options="dropdownOptions" align-x="end" />
    <k-loader v-if="isLoading" />
    <k-empty v-else-if="!modules.length" icon="box" @click="add()">
      {{ empty }}
    </k-empty>
    <k-draggable v-else :list="modules" :options="dragOptions" @sort="onSort" class="k-modules-list">
      <div v-for="module in modules" :key="module.id" class="k-module" :data-module-id="module.id"
        :data-selected="selectedModule === module.id" :data-disabled="isDisabled(module)"
        :tabindex="isDisabled(module) ? null : 0" @focusin.stop="select(module)">
        <div class="k-module-body" :data-collapsed="!isExpanded(module.id)">
          <header class="k-module-header">
            <button class="k-module-title" @click="toggle(module)">
              <k-icon v-if="loadingModules[module.id]" type="loader" />
              <span v-else class="k-module-icon">
                <k-icon :type="module.icon" />
                <k-icon :type="isExpanded(module.id) ? 'angle-up' : 'angle-down'" />
              </span>
              <span>{{ module.moduleName }}</span>
            </button>
            <k-button v-bind="statusButton(module)" @click.stop="toggleVisibility(module)" />
          </header>
          <k-drawer-tabs :tab="activeTabName(module)" :tabs="drawerTabs(module)" @open="switchTab(module, $event)" />
          <div v-if="isContentReady(module.id)" class="k-module-content">
            <k-sections v-for="tab in module.tabs" v-show="isExpanded(module.id) && activeTabName(module) === tab.name"
              :key="tab.name" :parent="pageUrl(module.id)" :tab="tab" :content="currentValues(module.id)"
              @input="onInput(module, $event)" />
          </div>
          <k-empty v-if="fieldData[module.id]?.error" icon="alert" layout="cardlets" class="k-module-error">
            {{ $t("error") }}
          </k-empty>
        </div>

        <k-toolbar v-if="selectedModule === module.id && !isDisabled(module)" :buttons="moduleToolbar(module)"
          data-inline="true" class="k-module-toolbar" @mousedown.native.prevent />
      </div>
    </k-draggable>
    <footer v-if="!isLoading && modules.length">
      <k-button icon="add" size="xs" variant="filled" @click="add()" />
    </footer>
  </k-section>
</template>

<script>
export default {
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
      activeTabs: {},
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

        // Invalidate field data for modules whose template changed
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

        this.trackPendingChanges();
        this.insertNewModule(previousIds);
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
    trackPendingChanges() {
      let hasPending = false;
      for (const module of this.modules) {
        if (module.hasPendingChanges) {
          this.$set(this.changes, module.id, true);
          hasPending = true;
        }
      }
      if (hasPending) {
        this.dirtyParent();
      }
    },
    insertNewModule(previousIds) {
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
      if (module.status === "draft") return;

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
      const ids = this.modules.filter((m) => m.status !== "draft").map((m) => m.id);
      try {
        await this.$api.post(this.sectionUrl + "/sort", { ids });
      } catch (e) {
        this.handleError(e);
      }
      this.fetch();
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

    // --- Field change tracking ---
    async onInput(module, values) {
      const data = this.fieldData[module.id];
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
      if (
        this.changes[moduleId] &&
        typeof this.changes[moduleId] === "object"
      ) {
        return this.changes[moduleId];
      }
      if (this.fieldData[moduleId]) {
        return this.fieldData[moduleId].values || {};
      }
      return {};
    },
    dirtyParent() {
      this.$panel.content.merge({ _modulesChanged: String(Date.now()) });
    },
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
    isExpanded(moduleId) {
      return this.expanded[moduleId] === true;
    },
    storageKey() {
      return "kirby-modules:" + this.parent;
    },
    saveCollapsedState() {
      const ids = Object.keys(this.expanded).filter(
        (id) => !this.expanded[id],
      );
      localStorage.setItem(this.storageKey(), JSON.stringify(ids));
    },
    loadCollapsedState() {
      try {
        const stored = localStorage.getItem(this.storageKey());
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
    statusButton(module) {
      const isDraft = module.status === "draft";
      return {
        ...this.$helper.page.status(
          module.status,
          module.permissions.changeStatus === false,
        ),
        text: isDraft ? this.$t("page.status.draft") : this.$t("page.status.listed"),
        variant: "filled",
        size: 'sm'
      };
    },
    moduleToolbar(module) {
      const isDraft = module.status === "draft";
      return [
        {
          icon: "edit",
          title: this.$t("edit"),
          click: () => this.$go(module.link),
        },
        {
          icon: "add",
          title: this.$t("modules.addBelow"),
          click: () => this.addAt(module, 1),
        },
        {
          icon: "trash",
          title: this.$t("delete"),
          click: () => this.remove(module),
        },
        {
          icon: "sort",
          title: this.$t("sort"),
          disabled: isDraft,
          class: isDraft ? "" : "k-sort-handle",
          key: isDraft ? undefined : (e) => {
            if (e.key === "ArrowUp") { e.preventDefault(); this.sortModule(module, -1); }
            if (e.key === "ArrowDown") { e.preventDefault(); this.sortModule(module, 1); }
          },
        },
        {
          icon: "dots",
          dropdown: [
            {
              icon: "edit",
              label: this.$t("edit"),
              click: () => this.$go(module.link),
            },
            "-",
            {
              icon: "add-module-above",
              label: this.$t("modules.addAbove"),
              click: () => this.addAt(module, 0),
            },
            {
              icon: "add-module-below",
              label: this.$t("modules.addBelow"),
              click: () => this.addAt(module, 1),
            },
            "-",
            {
              icon: "template",
              label: this.$t("field.blocks.changeType"),
              click: () => this.changeType(module),
            },
            {
              icon: "copy",
              label: this.$t("duplicate"),
              click: () => this.duplicate(module),
            },
            "-",
            {
              icon: this.isExpanded(module.id) ? "collapse" : "expand",
              label: this.isExpanded(module.id)
                ? this.$t("collapse")
                : this.$t("expand"),
              click: () => this.toggle(module),
            },
            {
              icon: isDraft ? "preview" : "hidden",
              label: isDraft ? this.$t("publish") : this.$t("modules.unpublish"),
              click: () => this.toggleVisibility(module),
            },
            "-",
            {
              icon: "trash",
              label: this.$t("delete"),
              click: () => this.remove(module),
            },
          ],
        },
      ];
    },
    select(module) {
      this.selectedModule = module.id;
    },
    onClickOutside(e) {
      if (e.target.closest(".k-module, .k-dialog, .k-drawer")) return;
      this.selectedModule = null;
    },
    isContentReady(moduleId) {
      const module = this.modules.find((m) => m.id === moduleId);
      if (!module || !module.hasFields) return true;
      return !!(
        this.fieldData[moduleId] && this.fieldData[moduleId].values
      );
    },
    isDisabled(module) {
      return (
        (module.lock && module.lock.isLocked) ||
        !(module.permissions && module.permissions.update)
      );
    },
    activeTabName(module) {
      return (
        this.activeTabs[module.id] ||
        (module.tabs[0] && module.tabs[0].name)
      );
    },
    switchTab(module, tabName) {
      this.$set(this.activeTabs, module.id, tabName);
    },
    drawerTabs(module) {
      return module.tabs.map(({ link, ...tab }) => tab);
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
.k-module {
  --module-color-back: light-dark(var(--color-white), var(--color-gray-850));
  position: relative;
  background: var(--module-color-back);
  box-shadow: var(--shadow);
  border-radius: var(--rounded);

  &[data-selected="true"] {
    z-index: 2;
    outline: var(--outline);
  }

  &[data-disabled="true"] {
    pointer-events: none;
    opacity: 0.5;
  }

  &:is(.k-sortable-ghost, .k-sortable-fallback) .k-module-body {
    position: relative;
    max-height: 4rem;
    overflow: hidden;

    &::after {
      position: absolute;
      bottom: 0;
      content: "";
      height: 2rem;
      width: 100%;
      background: linear-gradient(to top, var(--module-color-back), transparent);
    }
  }

  &.k-sortable-ghost {
    outline: 2px solid var(--color-focus);
    box-shadow: rgba(17, 17, 17, 0.25) 0 5px 10px;
    cursor: grabbing;
  }
}

.k-modules-list>.k-module+.k-module {
  margin-top: var(--spacing-2);
}

.k-module-toolbar {
  --toolbar-size: 30px;
  display: none;
  position: absolute;
  top: 0;
  inset-inline-end: var(--spacing-3);
  margin-top: calc(-1.75rem + 2px);
  box-shadow: var(--shadow-xl);
  border: 1px solid light-dark(var(--color-border), var(--color-gray-900));

  .k-module[data-selected="true"]>& {
    display: flex;
  }

  &>.k-button:not(:last-of-type) {
    border-inline-end: 1px solid var(--toolbar-border);
  }
}

.k-module-body {
  &:not([data-collapsed="true"]) {
    padding-bottom: var(--spacing-3);
  }

  &[data-collapsed="true"] {

    .k-module-content,
    .k-drawer-tabs {
      display: none;
    }
  }
}

.k-module-header {
  display: flex;
  align-items: center;
  padding-inline-end: var(--spacing-3);
}

.k-module-header .k-button {
  margin-inline-start: auto;
}

.k-module-body>.k-drawer-tabs {
  justify-content: flex-start;
  padding-inline-start: var(--spacing-3);
}

.k-module-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3);
  border-radius: var(--rounded);

  &:is(:hover, :focus-visible) .k-module-icon {
    > :first-child {
      opacity: 0;
    }

    > :last-child {
      opacity: 1;
    }
  }
}

.k-module-title .k-icon {
  color: var(--color-gray-500);
}

.k-module-icon {
  display: grid;

  >* {
    grid-area: 1 / 1;
  }

  > :last-child {
    opacity: 0;
  }
}

.k-module-content {
  background-color: var(--panel-color-back);
  border-radius: var(--rounded-sm);
  padding: var(--spacing-6) var(--spacing-6) var(--spacing-8);
  margin: 0 var(--spacing-3);
  container: column / inline-size;
}

.k-module-content>.k-grid {
  gap: var(--spacing-6);
}

.k-module-error {
  margin: 0 var(--spacing-3);
}

.k-topbar-breadcrumb li:has(a[href$="+modules"]) {
  display: none;
}

.k-section-name-modules footer {
  display: flex;
  justify-content: center;
  margin-top: var(--spacing-3);
}
</style>
