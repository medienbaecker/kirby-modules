<template>
  <k-section class="k-modules-section" :headline="headline" :buttons="sectionButtons" :required="Boolean(min)"
    :invalid="isInvalid">
    <k-dropdown-content ref="options" :options="dropdownOptions" align-x="end" />
    <k-loader v-if="isLoading" />
    <k-empty v-else-if="!modules.length" icon="box" @click="add()">
      {{ empty }}
    </k-empty>
    <k-draggable v-else :list="modules" :options="dragOptions" @sort="onSort" class="k-modules-list">
      <k-module-card v-for="module in modules" :key="module.id + ':' + module.template" :module="module"
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

// The dirty marker (see dirtyParent) must never reach the server:
// unknown fields in a save/publish payload pass through into the
// content file. This wraps panel.content.request() once.
function keepDirtyMarkersOutOfPayloads(panel) {
  const content = panel.content;
  if (!content || typeof content.request !== "function" || content.request._stripsMarkers) {
    return;
  }
  const original = content.request.bind(content);
  content.request = (method, values = {}, env = {}) => {
    const clean = Object.fromEntries(
      Object.entries(values).filter(([key]) => !key.startsWith("_modulesChanged_")),
    );
    return original(method, clean, env);
  };
  content.request._stripsMarkers = true;
}

export default {
  components: {
    "k-module-card": ModuleCard,
  },

  props: {
    name: String,
    parent: String,
    timestamp: Number,
    // The host page's lock state, passed by k-sections
    lock: Object,
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
    isHostLocked() {
      return this.lock?.isLocked === true;
    },
    sectionButtons() {
      if (this.isHostLocked) return [];
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
    keepDirtyMarkersOutOfPayloads(this.$panel);

    // Captured for cache invalidation on language switch in fetch().
    this._language = this.$panel.language?.code;

    // Show the label before the first fetch resolves instead of leaving it blank.
    this.headline = this.$t("modules.plural");

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
            this.$api.post(this.pageUrl(module.id) + "/changes/discard", {}, { silent: true }).catch(() => { });
          }
        }

        const collapsed = this.loadCollapsedState();
        const toLoad = this.modules.filter((m) => this.restoreExpandState(m, collapsed));

        this.reconcileState();
        this.positionNewModule(previousIds);

        this.loadFieldsBatch(toLoad, true);
      } catch (e) {
        this.handleError(e);
      } finally {
        this.isLoading = false;
      }
    },

    // Returns true when fields still need loading (deferred to a batched
    // request); otherwise sets the expand state directly.
    restoreExpandState(module, collapsed) {
      if (collapsed.includes(module.id)) {
        this.$set(this.expanded, module.id, false);
        return false;
      }

      // Fields must load before k-sections can render them, so defer the expand.
      if (module.hasFields && (!this.fieldData[module.id] || module.hasPendingChanges)) {
        return true;
      }
      this.$set(this.expanded, module.id, true);
      return false;
    },

    reconcileState() {
      const currentIds = new Set(this.modules.map((m) => m.id));
      const trackingMaps = [this.changes, this.fieldData, this.expanded, this.loadingModules];

      for (const map of trackingMaps) {
        for (const id of Object.keys(map)) {
          if (!currentIds.has(id)) this.$delete(map, id);
        }
      }

      // Don't adopt another user's changes while they hold the page lock
      if (this.isHostLocked) {
        this.serverPendingIds = [];
        this.undirtyParent();
        return;
      }

      // Server-side _changes not tracked locally (e.g. from a previous session)
      this.serverPendingIds = this.modules
        .filter((m) => m.hasPendingChanges && !this.changes[m.id] && !m.isLocked)
        .map((m) => m.id);

      this.syncDirtyState();
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
            this.focusFirstInput(el);
          }
        });
      }
      this.pendingInsertPosition = null;
    },

    // Mirror Kirby's internal drawer/dialog focus priority: explicit
    // [autofocus] first, then first form control. Polls because the
    // module's fields load asynchronously.
    focusFirstInput(el) {
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
    },

    // Batched so loading values isn't one request per module.
    async loadFieldsBatch(modules, reveal = false) {
      for (const m of modules) this.$set(this.loadingModules, m.id, true);
      const CHUNK = 30;
      const tasks = [];
      for (let i = 0; i < modules.length; i += CHUNK) {
        const chunk = modules.slice(i, i + CHUNK);
        tasks.push(
          this.loadFieldsChunk(chunk).then(() => {
            for (const m of chunk) {
              this.$delete(this.loadingModules, m.id);
              if (reveal) this.$set(this.expanded, m.id, true);
            }
          }),
        );
      }
      await Promise.all(tasks);
    },

    async loadFieldsChunk(modules) {
      try {
        const response = await this.$api.post(this.sectionUrl + "/fields", {
          ids: modules.map((m) => m.id),
        });
        for (const module of modules) {
          const entry = response[module.id];
          if (!entry || entry.error) {
            this.$set(this.fieldData, module.id, { error: true });
            continue;
          }
          this.$set(this.fieldData, module.id, {
            values: entry.values,
            original: JSON.stringify(entry.values),
          });
          if (entry.moduleName !== undefined) module.moduleName = entry.moduleName;
        }
      } catch (e) {
        this.handleError(e);
        for (const module of modules) {
          this.$set(this.fieldData, module.id, { error: true });
        }
      }
    },

    add(position = -1) {
      if (!this.canAdd || this.isHostLocked) return;
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
          await this.queueChanges(module.id, () =>
            this.$api.post(this.pageUrl(module.id) + "/changes/save", pending, { silent: true }),
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
      this.$dialog("modules/change-type/" + this.encodeId(module.id), {
        on: {
          // Clear cached fields before refetch so the old k-sections unmounts
          // instead of fetching the old template's section against the new one.
          success: () => {
            this.$delete(this.fieldData, module.id);
            this.$delete(this.changes, module.id);
            this.$panel.dialog.close();
            this.fetch();
          },
        },
      });
    },
    changeSlug(module) {
      this.$dialog("modules/change-slug/" + this.encodeId(module.id));
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

    // Per-module promise chain: concurrent saves could land out of
    // order, and a publish could beat an in-flight save to the disk.
    queueChanges(moduleId, request) {
      const chains = (this._changesChains ??= {});
      const next = (chains[moduleId] || Promise.resolve()).then(request, request);
      chains[moduleId] = next.catch(() => { });
      return next;
    },

    // Module changes are saved to each module's _changes version immediately,
    // then published/discarded when the parent page is saved/discarded.
    async onInput(module, values) {
      const data = this.fieldData[module.id];
      const unchanged = data?.original && JSON.stringify(values) === data.original;

      if (unchanged) {
        this.$delete(this.changes, module.id);
        this.queueChanges(module.id, () =>
          this.$api.post(this.pageUrl(module.id) + "/changes/discard", {}, { silent: true }),
        ).catch(() => { });
      } else {
        try {
          await this.queueChanges(module.id, () =>
            this.$api.post(this.pageUrl(module.id) + "/changes/save", values, { silent: true }),
          );
          // Commit local state only after the server accepts; otherwise a
          // 423 leaves the parent dirty with nothing the user can publish.
          this.$set(this.changes, module.id, values);
        } catch (e) {
          this.handleError(e);
          return;
        }
      }

      this.syncDirtyState();
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
            this.queueChanges(moduleId, () =>
              this.$api.post(this.pageUrl(moduleId) + "/changes/" + action),
            ),
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
          await this.loadFieldsBatch(
            this.modules.filter(
              (m) => succeeded.includes(m.id) && this.expanded[m.id] && m.hasFields,
            ),
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

    // Section-scoped key — multiple modules sections dirty independently.
    // Never persisted: keepDirtyMarkersOutOfPayloads() strips it.
    dirtyParent() {
      this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: String(Date.now()) });
    },
    undirtyParent() {
      this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: undefined });
    },
    syncDirtyState() {
      const hasChanges =
        this.serverPendingIds.length > 0 || Object.keys(this.changes).length > 0;
      if (hasChanges) {
        this.dirtyParent();
      } else {
        this.undirtyParent();
      }
    },

    // Collapsed state persists in localStorage per page + section name.
    async toggle(module) {
      if (this.expanded[module.id]) {
        this.$set(this.expanded, module.id, false);
        this.saveCollapsedState();
        return;
      }
      if (module.hasFields && !this.fieldData[module.id]) {
        await this.loadFieldsBatch([module]);
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
      await this.loadFieldsBatch(toLoad);
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
        // Reload like core's lock alert: brings in the lock prop, so
        // the section dims instead of silently reverting the edit
        this.$panel.view.reload();
        return;
      }
      // notification.error unwraps Error/RequestError objects itself
      this.$panel.notification.error(e || this.$t("error"));
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
/* Same dimming core applies to fields sections on a locked page */
[data-locked="true"] .k-modules-section {
  opacity: 0.2;
  pointer-events: none;
}

.k-module+.k-module {
  margin-block-start: var(--spacing-2);
}

footer {
  display: flex;
  justify-content: center;
  margin-block-start: var(--spacing-3);
}
</style>
