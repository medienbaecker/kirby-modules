<template>
  <k-field :id="id" :class="['k-modules-field', $attrs.class]" :help="help" :input="false" :label="label"
    :name="name" :required="Boolean(min)" :style="$attrs.style" :type="type">
    <template v-if="buttons.length" #options>
      <k-button-group :buttons="buttons" size="xs" variant="filled" />
    </template>
    <k-dropdown ref="options" :options="dropdownOptions" align-x="end" />
    <k-validator v-if="!isLoading" class="k-modules-validator" :count="modules.length" :max="max" :min="min">
      <k-draggable :list="modules" :options="dragOptions" :group="'k-modules:' + parent" :data="{ accepts }"
        :move="onMove" @change="onDragChange" @start="onDragStart" @end="onDragEnd" class="k-modules-list"
        :data-layout="layout">
        <k-module-card v-for="module in modules" :key="module.id + ':' + module.template" :module="module"
          :expanded="expanded[module.id] === true" :loading="!!loadingModules[module.id]"
          :selected="selectedModule === module.id" :values="currentValues(module.id)"
          :columns="fieldData[module.id]?.columns" :page-url="$api.pages.url(module.id)"
          :has-error="!!(fieldData[module.id] && fieldData[module.id].error)" :is-full="!canAdd" @toggle="toggle(module)"
          @toggle-visibility="toggleVisibility(module)" @select="select(module)" @input="onInput(module, $event)"
          @add="addAt(module, $event)" @remove="remove(module)" @duplicate="duplicate(module)"
          @move="moveModule(module)" @change-type="changeType(module)" @change-slug="changeSlug(module)"
          @sort="sortModule(module, $event)" />
      </k-draggable>
    </k-validator>
    <k-empty v-if="isLoading" class="k-modules-empty" icon="loader">
      {{ $t("loading") }}
    </k-empty>
    <k-empty v-else class="k-modules-empty" icon="box" @click="add()">
      {{ empty }}
    </k-empty>
    <footer v-if="!isLoading && modules.length && canAdd && !isLocked" class="k-modules-footer">
      <k-button icon="add" size="xs" variant="filled" :title="$t('add')" @click="add()" />
    </footer>
  </k-field>
</template>

<script>
import ModuleCard from "./ModuleCard.vue";

// Fields don't receive a new timestamp when the view reloads, so the
// list refreshes itself after every action that can change it, like
// core's pages field.
const REFRESH_EVENTS = [
  "model.update",
  "page.changeSlug",
  "page.changeStatus",
  "page.changeTemplate",
  "page.changeTitle",
  "page.create",
  "page.delete",
  "page.duplicate",
];

const moduleRequests = new Set();
const pageRequests = new Set();
let pageRequestsStarted = 0;

// The dirty marker (see dirtyParent) must never reach the server:
// unknown fields in a save/publish payload pass through into the
// content file.
function wrapContentRequest(panel) {
  const content = panel.content;
  if (content.request._wrapped) return;
  const original = content.request.bind(content);
  content.request = (method = "save", values = {}, env = {}) => {
    const clean = Object.fromEntries(
      Object.entries(values).filter(([key]) => !key.startsWith("_modulesChanged_")),
    );
    if (method === "save") return original(method, clean, env);

    const request = Promise.allSettled(moduleRequests)
      .then(() => original(method, clean, env))
      .finally(() => pageRequests.delete(request));
    pageRequests.add(request);
    pageRequestsStarted++;
    return request;
  };
  content.request._wrapped = true;
}

export default {
  components: {
    "k-module-card": ModuleCard,
  },

  inheritAttrs: false,

  props: {
    disabled: Boolean,
    empty: String,
    // Field and model API endpoints, passed by k-model-form
    endpoints: Object,
    help: String,
    id: String,
    label: String,
    layout: String,
    max: Number,
    min: Number,
    name: String,
    templates: {
      type: Array,
      default: () => [],
    },
    type: String,
  },

  // The field holds no value and never emits these events. They must still
  // be declared: Kirby binds undeclared listeners of components without
  // inherited attrs to the root element, so native `input` and `submit`
  // events from the module forms would bubble into the host form.
  emits: ["blur", "focus", "input", "submit"],

  data() {
    return {
      modules: [],

      expanded: {},
      fieldData: {},
      changes: {},
      drafts: {},
      serverPendingIds: [],
      descendantPendingIds: [],
      loadingModules: {},

      isLoading: true,
      selectedModule: null,
      pendingInsert: null,
      dragScrollShift: 0,
    };
  },

  computed: {
    dragOptions() {
      return {
        handle: ".k-sort-handle",
        draggable: ".k-module",
        // Grabbing shrinks the page; at the bottom the browser scrolls up. Undo that.
        fallbackOffset: { x: 0, y: this.dragScrollShift },
        onChoose: () => (this._scrollAtGrab = window.scrollY),
        emptyInsertThreshold: 40,
      };
    },
    storageKey() {
      return "kirby-modules:" + this.parent + ":" + this.name;
    },
    canAdd() {
      return !this.max || this.modules.length < this.max;
    },
    fieldUrl() {
      return this.endpoints.field;
    },
    // API path of the page or site that holds the field
    parent() {
      return this.endpoints.model.replace(/^\//, "");
    },
    // The view's model (the host page) is locked by another user
    isHostLocked() {
      return this.$panel.view.props.lock?.isLocked === true;
    },
    isLocked() {
      return this.disabled || this.isHostLocked;
    },
    buttons() {
      if (this.isLocked || this.isLoading) return [];
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
                await this.$api.post(this.fieldUrl + "/deleteAll");
                this.$panel.dialog.close();
                this.fetch();
              },
            },
          }),
          disabled: !this.modules.length,
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
    "$panel.language.code"() {
      this.fieldData = {};
      this.changes = {};
      this.fetch();
    },
  },
  created() {
    wrapContentRequest(this.$panel);

    // Field name = container slug; create it on first mount if missing.
    this.$api.post(this.fieldUrl + "/create-container").then(() => this.fetch());

    this._onRefresh = this.$helper.debounce(async () => {
      await Promise.allSettled(pageRequests);
      this.fetch();
    }, 0);

    for (const event of REFRESH_EVENTS) {
      this.$events.on(event, this._onRefresh);
    }

    this._onPublish = () => this.settleChanges("publish");
    this._onDiscard = () => this.settleChanges("discard");
    this._onMoved = (event) => {
      if (!event?.parent || event.parent === this.parent) this.fetch();
    };
    this._onDescendantDirty = ({ parent, dirty }) => this.handleDescendantDirty(parent, dirty);
    this._onRemoteUpdate = ({ api, values }) => {
      const module = this.moduleByApi(api);
      if (!this.fieldData[module?.id]?.columns) return;
      this.onInput(module, { ...this.currentValues(module.id), ...values }, true);
    };
    this._onRemoteMerge = ({ api, values }) => {
      const module = this.moduleByApi(api);
      if (!this.fieldData[module?.id]?.columns) return;
      this.mergeValues(module, { ...this.currentValues(module.id), ...values });
    };
    this.$events.on("modules.update", this._onRemoteUpdate);
    this.$events.on("modules.merge", this._onRemoteMerge);
    this.$events.on("modules.moved", this._onMoved);
    this.$events.on("content.publish", this._onPublish);
    this.$events.on("content.discard", this._onDiscard);
    this.$events.on("modules.dirty", this._onDescendantDirty);
    this.$events.on("page.delete", this.notifyPreview);
  },
  mounted() {
    document.addEventListener("mousedown", this.onClickOutside);
  },
  unmounted() {
    for (const event of REFRESH_EVENTS) {
      this.$events.off(event, this._onRefresh);
    }
    this.$events.off("modules.update", this._onRemoteUpdate);
    this.$events.off("modules.merge", this._onRemoteMerge);
    this.$events.off("modules.moved", this._onMoved);
    this.$events.off("content.publish", this._onPublish);
    this.$events.off("content.discard", this._onDiscard);
    this.$events.off("modules.dirty", this._onDescendantDirty);
    this.$events.off("page.delete", this.notifyPreview);
    document.removeEventListener("mousedown", this.onClickOutside);
  },

  methods: {

    async fetch() {
      try {
        const previousIds = new Set(this.modules.map((m) => m.id));
        const previousTemplates = new Map(this.modules.map((m) => [m.id, m.template]));

        const started = pageRequestsStarted;
        const response = await this.$api.get(this.fieldUrl);
        if (pageRequestsStarted !== started) {
          this._onRefresh();
          return;
        }
        this.modules = response.modules;

        for (const module of this.modules) {
          const prev = previousTemplates.get(module.id);
          if (prev && prev !== module.template) {
            delete this.fieldData[module.id];
            delete this.changes[module.id];
            module.hasPendingChanges = false;
            this.$api.post(this.$api.pages.url(module.id, "changes/discard"), {}, { silent: true }).catch(() => { });
          }
        }

        const collapsed = this.loadCollapsedState();
        const toLoad = this.modules.filter((m) => this.restoreExpandState(m, collapsed));

        this.reconcileState();
        this.positionNewModule(previousIds);

        this.loadFieldsBatch(toLoad, true);
      } catch (e) {
        if (e?.response?.status === 404) return;
        this.handleError(e);
      } finally {
        this.isLoading = false;
      }
    },

    // Returns true when fields still need loading (deferred to a batched
    // request); otherwise sets the expand state directly.
    restoreExpandState(module, collapsed) {
      if (collapsed.includes(module.id)) {
        this.expanded[module.id] = false;
        return false;
      }

      // Fields must load before k-model-form can render them, so defer the expand.
      if (module.hasFields && (!this.fieldData[module.id] || module.hasPendingChanges)) {
        return true;
      }
      this.expanded[module.id] = true;
      return false;
    },

    reconcileState() {
      const currentIds = new Set(this.modules.map((m) => m.id));
      const trackingMaps = [this.changes, this.fieldData, this.expanded, this.loadingModules];

      for (const map of trackingMaps) {
        for (const id of Object.keys(map)) {
          if (!currentIds.has(id)) delete map[id];
        }
      }
      this.descendantPendingIds = this.descendantPendingIds.filter((id) => currentIds.has(id));

      // Don't adopt another user's changes while they hold the page lock
      if (this.isHostLocked) {
        this.serverPendingIds = [];
        this.descendantPendingIds = [];
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
      if (!this.pendingInsert) return;
      const { position, focusInput } = this.pendingInsert;
      this.pendingInsert = null;

      const newModule = this.modules.find((m) => !previousIds.has(m.id));
      if (!newModule) return;

      const others = this.modules.filter((m) => m !== newModule);
      others.splice(position >= 0 ? position : others.length, 0, newModule);
      this.modules = others;
      this.onSort();
      this.$nextTick(() => {
        const el = this.$el.querySelector(`[data-module-id="${newModule.id}"]`);
        if (!el) return;
        el.focus();
        if (focusInput) this.focusFirstInput(el);
      });
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
      for (const m of modules) this.loadingModules[m.id] = true;
      const CHUNK = 30;
      const tasks = [];
      for (let i = 0; i < modules.length; i += CHUNK) {
        const chunk = modules.slice(i, i + CHUNK);
        tasks.push(
          this.loadFieldsChunk(chunk).then(() => {
            for (const m of chunk) {
              delete this.loadingModules[m.id];
              if (reveal) this.expanded[m.id] = true;
            }
          }),
        );
      }
      await Promise.all(tasks);
    },

    async loadFieldsChunk(modules) {
      try {
        const response = await this.$api.post(this.fieldUrl + "/fields", {
          ids: modules.map((m) => m.id),
        });
        for (const module of modules) {
          const entry = response[module.id];
          if (!entry || entry.error) {
            this.fieldData[module.id] = { error: true };
            continue;
          }
          this.fieldData[module.id] = {
            columns: entry.columns,
            values: entry.values,
            original: JSON.stringify(entry.original),
          };
          module.moduleName = entry.moduleName;
        }
      } catch (e) {
        this.handleError(e);
        for (const module of modules) {
          this.fieldData[module.id] = { error: true };
        }
      }
      for (const module of modules) {
        const columns = this.fieldData[module.id]?.columns;
        if (!columns) continue;
        this.$events.emit("modules.load", {
          ...this.eventPayload(module),
          values: this.currentValues(module.id),
          columns: Object.values(columns).flatMap((tab) => Object.values(tab)),
        });
      }
    },

    add(position = -1) {
      if (!this.canAdd || this.isLocked) return;
      this.pendingInsert = { position, focusInput: true };
      this.$dialog(this.fieldUrl + "/create");
    },
    addAt(module, offset) {
      const index = this.modules.findIndex((m) => m.id === module.id);
      this.add(index + offset);
    },
    async duplicate(module) {
      try {
        // Flush any pending client-side edits so they land in the server's
        // _changes/ before the duplicate endpoint copies that directory.
        // Without this, a fast "type then duplicate" produces an empty copy.
        const pending = this.drafts[module.id] ?? this.changes[module.id];
        const original = this.fieldData[module.id]?.original;
        if (pending && JSON.stringify(pending) !== original) {
          await this.queueChanges(module.id, () =>
            this.$api.post(this.$api.pages.url(module.id, "changes/save"), pending, { silent: true }),
          );
        }
        await this.$api.post(
          this.fieldUrl + "/duplicate/" + this.$api.pages.id(module.id),
        );
        const index = this.modules.findIndex((m) => m.id === module.id);
        this.pendingInsert = { position: index >= 0 ? index + 1 : -1, focusInput: false };
        this.fetch();
      } catch (e) {
        this.handleError(e);
      }
    },
    changeType(module) {
      this.$dialog("modules/change-type/" + this.$api.pages.id(module.id), {
        on: {
          success: () => {
            this.$panel.dialog.close();
            this.fetch();
            this.notifyPreview();
          },
        },
      });
    },
    moveModule(module) {
      this.$dialog("modules/move/" + this.$api.pages.id(module.id));
    },

    changeSlug(module) {
      this.$dialog("modules/change-slug/" + this.$api.pages.id(module.id));
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

    onMove(event) {
      return event.toData.accepts(event.draggedData.module.template);
    },

    accepts(template) {
      return this.templates.includes(template) && this.canAdd;
    },

    onDragStart() {
      this.dragScrollShift = window.scrollY - this._scrollAtGrab;
    },

    onDragEnd() {
      this.dragScrollShift = 0;
    },

    onDragChange(event) {
      if (event.moved) return this.onSort();
      if (event.added) return this.onDrop(event.added.element);
    },

    async onDrop(module) {
      const ids = this.modules.map((m) => m.id);
      try {
        await this.$api.post(this.fieldUrl + "/move", { id: module.id, ids });
        this.$events.emit("page.sort");
      } catch (e) {
        this.handleError(e);
      }
      this.$events.emit("modules.moved", { parent: this.parent });
    },

    async onSort() {
      const ids = this.modules.map((m) => m.id);
      try {
        await this.$api.post(this.fieldUrl + "/sort", { ids });
        this.$events.emit("page.sort");
      } catch (e) {
        this.handleError(e);
      }
      await this.fetch();
    },

    async toggleVisibility(module) {
      try {
        await this.$api.post(
          this.fieldUrl + "/toggle-visibility/" + this.$api.pages.id(module.id),
        );
        this.$events.emit("model.update");
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
      this.$dialog(this.$api.pages.url(module.id, "delete"), {
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
      moduleRequests.add(next);
      next.finally(() => moduleRequests.delete(next)).catch(() => { });
      return next;
    },

    // Module changes are saved to each module's _changes version immediately,
    // then published/discarded when the parent page is saved/discarded.
    async onInput(module, values, remote = false) {
      if (!remote) this.$events.emit("modules.input", { ...this.eventPayload(module), values });

      const data = this.fieldData[module.id];
      const isOriginal = (v) => JSON.stringify(v) === data.original;

      // The form shows these values right away. Saves are queued, so the
      // form would otherwise snap back to older values while typing.
      this.drafts[module.id] = values;
      this._draftSeq ??= {};
      const seq = (this._draftSeq[module.id] = (this._draftSeq[module.id] ?? 0) + 1);
      const settle = () => {
        if (this._draftSeq[module.id] !== seq) return;
        delete this.drafts[module.id];
        this.notifyPreview();
      };

      if (isOriginal(values)) {
        delete this.changes[module.id];
        this.queueChanges(module.id, () =>
          this.$api.post(this.$api.pages.url(module.id, "changes/discard"), {}, { silent: true }),
        ).catch(() => { }).finally(settle);
      } else {
        try {
          await this.queueChanges(module.id, () =>
            this.$api.post(this.$api.pages.url(module.id, "changes/save"), values, { silent: true }),
          );
          // Commit local state only after the server accepts; otherwise a
          // 423 leaves the parent dirty with nothing the user can publish.
          if (!isOriginal(this.drafts[module.id] ?? values)) {
            this.changes[module.id] = values;
          }
        } catch (e) {
          settle();
          this.handleError(e);
          return;
        }
        settle();
      }

      this.syncDirtyState();
    },

    mergeValues(module, values) {
      if (JSON.stringify(values) === this.fieldData[module.id]?.original) {
        delete this.changes[module.id];
      } else {
        this.changes[module.id] = values;
      }
      if (this.drafts[module.id]) this.drafts[module.id] = values;
      this.syncDirtyState();
    },

    async settleChanges(action) {
      const ids = new Set([
        ...Object.keys(this.changes),
        ...this.serverPendingIds,
        ...this.descendantPendingIds,
      ]);
      for (const id of Object.keys(this.changes)) {
        if (action === "publish") this.markPublished(id);
        else delete this.changes[id];
      }
      this.serverPendingIds = [];
      this.descendantPendingIds = [];
      this.syncDirtyState();

      await this.fetch();
      await this.loadFieldsBatch(
        this.modules.filter((m) => ids.has(m.id) && this.fieldData[m.id]),
      );
    },

    markPublished(moduleId) {
      const data = this.fieldData[moduleId];
      const values = this.changes[moduleId];
      if (data && values) {
        data.values = values;
        data.original = JSON.stringify(values);
      }
      delete this.changes[moduleId];
    },

    currentValues(moduleId) {
      return this.drafts[moduleId]
        || this.changes[moduleId]
        || this.fieldData[moduleId]?.values
        || {};
    },

    // Field-scoped key — multiple modules fields dirty independently.
    // Never persisted: wrapContentRequest() strips it.
    dirtyParent() {
      this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: String(Date.now()) });
    },
    undirtyParent() {
      const versions = this.$panel.content.versions();
      delete versions.changes[`_modulesChanged_${this.name}`];
      delete versions.latest[`_modulesChanged_${this.name}`];
    },
    syncDirtyState() {
      const hasChanges =
        this.serverPendingIds.length > 0 ||
        Object.keys(this.changes).length > 0 ||
        this.descendantPendingIds.length > 0;

      if (hasChanges) {
        this.dirtyParent();
      } else {
        this.undirtyParent();
      }

      if (this._lastReportedDirty !== hasChanges) {
        this._lastReportedDirty = hasChanges;
        this.$events.emit("modules.dirty", { parent: this.parent, dirty: hasChanges });
      }
    },

    handleDescendantDirty(parentApi, dirty) {
      const owner = this.moduleByApi(parentApi);
      if (!owner) return;

      const tracked = this.descendantPendingIds.includes(owner.id);
      if (dirty && !tracked) {
        this.descendantPendingIds = [...this.descendantPendingIds, owner.id];
        this.syncDirtyState();
      } else if (!dirty && tracked) {
        this.descendantPendingIds = this.descendantPendingIds.filter((id) => id !== owner.id);
        this.syncDirtyState();
      }
    },

    // Collapsed state persists in localStorage per page + field name.
    async toggle(module) {
      const expand = !this.expanded[module.id];
      if (expand && module.hasFields && !this.fieldData[module.id]) {
        await this.loadFieldsBatch([module]);
      }
      this.expanded[module.id] = expand;
      this.saveCollapsedState();
    },
    saveCollapsedState() {
      const ids = Object.keys(this.expanded).filter(
        (id) => !this.expanded[id],
      );
      localStorage.setItem(this.storageKey, JSON.stringify(ids));
    },
    loadCollapsedState() {
      try {
        const stored = localStorage.getItem(this.storageKey);
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
        this.expanded[m.id] = true;
      }
      this.saveCollapsedState();
    },
    collapseAll() {
      for (const m of this.modules) {
        this.expanded[m.id] = false;
      }
      this.saveCollapsedState();
    },

    select(module) {
      this.selectedModule = module.id;
    },
    onClickOutside(e) {
      const clickedModule = e.target.closest(".k-module");
      if (clickedModule && this.$el.contains(clickedModule)) return;
      if (e.target.closest(".k-dialog, .k-drawer")) return;
      this.selectedModule = null;
    },

    moduleByApi(api) {
      api = api?.replace(/^\//, "");
      return this.modules.find((m) => this.$api.pages.url(m.id) === api);
    },
    handleError(e) {
      if (e?.details?.isLocked) {
        // Reload like core's lock alert: brings in the lock prop, so
        // the form dims instead of silently reverting the edit
        this.$panel.view.reload();
        return;
      }
      // notification.error unwraps Error/RequestError objects itself
      this.$panel.notification.error(e);
    },
    // Kirby's preview view only reloads on content and model events. The
    // field's own api matches no view, so nothing else reacts to it.
    notifyPreview() {
      this.$panel.content.emit("save", {}, { api: this.fieldUrl });
    },

    eventPayload(module) {
      return {
        field: this.name,
        parent: this.parent,
        id: module.id,
        api: this.$api.pages.url(module.id),
        template: module.template,
      };
    },
  },
};
</script>

<style scoped>
.k-modules-list {
  display: flex;
  flex-direction: column;
  gap: var(--module-gap, var(--spacing-2));
}

/* Sortable won't drop into a list with any child element. */
.k-modules-validator:has(> .k-modules-list > .k-module) + .k-modules-empty {
  display: none;
}

.k-modules-footer {
  display: flex;
  justify-content: center;
  margin-block-start: var(--spacing-3);
}
</style>
