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
          :columns="fieldData[module.id]?.columns" :page-url="pageUrl(module.id)"
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
      {{ empty ?? $t("modules.empty") }}
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

const runningApplies = new Set();
let appliesStarted = 0;

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
      canAdd: true,

      expanded: {},
      fieldData: {},
      changes: {},
      drafts: {},
      serverPendingIds: [],
      descendantPendingIds: [],
      loadingModules: {},

      isLoading: true,
      selectedModule: null,
      pendingInsertPosition: null,
      pendingFocusInput: false,
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
    // The field endpoint replaces the former section endpoint
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
      this.fetch();
    },
  },
  created() {
    keepDirtyMarkersOutOfPayloads(this.$panel);

    // Captured for cache invalidation on language switch in fetch().
    this._language = this.$panel.language?.code;

    // Field name = container slug; create it on first mount if missing.
    this.$api.post(this.fieldUrl + "/create-container").then(() => this.fetch());

    this._onRefresh = this.$helper.debounce(async () => {
      await Promise.all(runningApplies);
      this.fetch();
    }, 0);

    for (const event of REFRESH_EVENTS) {
      this.$events.on(event, this._onRefresh);
    }

    // Bridge module changes into the parent page's publish/discard flow.
    // The { api } guard ensures only this field's parent triggers it.
    this._onPublish = ({ api }) => {
      if (this.isParentApi(api)) this.applyChanges("publish");
    };
    this._onDiscard = ({ api }) => {
      if (this.isParentApi(api)) this.applyChanges("discard");
    };
    this._onMoved = (event) => {
      if (!event?.parent || event.parent === this.parent) this.fetch();
    };
    this._onDescendantDirty = ({ parent, dirty }) => this.handleDescendantDirty(parent, dirty);
    this._onModulePublished = async ({ parent }) => {
      if (!this.isDescendantApi(parent)) return;
      for (const id of Object.keys(this.changes)) this.markPublished(id);
      this.serverPendingIds = [];
      this.descendantPendingIds = [];
      this._lastReportedDirty = undefined;
      await this.fetch();
      await this.loadFieldsBatch(
        this.modules.filter((m) => this.expanded[m.id] && m.hasFields && this.fieldData[m.id]),
      );
    };
    this._onRemoteUpdate = ({ api, values }) => {
      const module = this.modules.find((m) => this.isParentApi(api, this.pageUrl(m.id)));
      if (!module || !this.fieldData[module.id]?.columns) return;
      this.onInput(module, { ...this.currentValues(module.id), ...values }, true);
    };
    this._onRemoteMerge = ({ api, values }) => {
      const module = this.modules.find((m) => this.isParentApi(api, this.pageUrl(m.id)));
      if (!module || !this.fieldData[module.id]?.columns) return;
      this.mergeValues(module, { ...this.currentValues(module.id), ...values });
    };
    this.$events.on("modules.update", this._onRemoteUpdate);
    this.$events.on("modules.merge", this._onRemoteMerge);
    this.$events.on("modules.moved", this._onMoved);
    this.$events.on("content.publish", this._onPublish);
    this.$events.on("content.discard", this._onDiscard);
    this.$events.on("modules.dirty", this._onDescendantDirty);
    this.$events.on("modules.published", this._onModulePublished);
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
    this.$events.off("modules.published", this._onModulePublished);
    this.$events.off("page.delete", this.notifyPreview);
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

        const started = appliesStarted;
        const response = await this.$api.get(this.fieldUrl);
        if (appliesStarted !== started) {
          this._onRefresh();
          return;
        }
        this.modules = response.modules;
        this.canAdd = response.add;

        for (const module of this.modules) {
          const prev = previousTemplates.get(module.id);
          if (prev && prev !== module.template) {
            delete this.fieldData[module.id];
            delete this.changes[module.id];
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
            original: JSON.stringify(entry.values),
          };
          if (entry.moduleName !== undefined) module.moduleName = entry.moduleName;
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
      this.pendingInsertPosition = position;
      this.pendingFocusInput = true;
      this.$dialog(this.fieldUrl + "/create");
    },
    addAt(module, offset) {
      const index = this.modules.findIndex((m) => m.id === module.id);
      this.add(index + offset);
    },
    async duplicate(module) {
      if (!this.canAdd) return;
      try {
        // Clear any stale focus intent from a previously cancelled Add.
        this.pendingFocusInput = false;
        // Flush any pending client-side edits so they land in the server's
        // _changes/ before the duplicate endpoint copies that directory.
        // Without this, a fast "type then duplicate" produces an empty copy.
        const pending = this.drafts[module.id] ?? this.changes[module.id];
        const original = this.fieldData[module.id]?.original;
        if (pending && JSON.stringify(pending) !== original) {
          await this.queueChanges(module.id, () =>
            this.$api.post(this.pageUrl(module.id) + "/changes/save", pending, { silent: true }),
          );
        }
        await this.$api.post(
          this.fieldUrl + "/duplicate/" + this.encodeId(module.id),
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
          // Clear cached fields before refetch so the old form unmounts
          // instead of rendering the old template's fields against the new one.
          success: () => {
            delete this.fieldData[module.id];
            delete this.changes[module.id];
            this.$panel.dialog.close();
            this.fetch();
            this.notifyPreview();
          },
        },
      });
    },
    moveModule(module) {
      this.$dialog("modules/move/" + this.encodeId(module.id));
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

    onMove(event) {
      return event.toData.accepts(event.draggedData.module.template);
    },

    accepts(template) {
      return (
        this.templates.includes(template) &&
        (!this.max || this.modules.length < this.max)
      );
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
          this.fieldUrl + "/toggle-visibility/" + this.encodeId(module.id),
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
    async onInput(module, values, remote = false) {
      if (!remote) this.$events.emit("modules.input", { ...this.eventPayload(module), values });

      const data = this.fieldData[module.id];
      const isOriginal = (v) => data?.original && JSON.stringify(v) === data.original;

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
          this.$api.post(this.pageUrl(module.id) + "/changes/discard", {}, { silent: true }),
        ).catch(() => { }).finally(settle);
      } else {
        try {
          await this.queueChanges(module.id, () =>
            this.$api.post(this.pageUrl(module.id) + "/changes/save", values, { silent: true }),
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

    // Triggered by parent page Save/Discard.
    applyChanges(action) {
      if (!this._applying) {
        const apply = this.sendChanges(action).finally(() => {
          runningApplies.delete(apply);
          this._applying = null;
        });
        runningApplies.add(apply);
        appliesStarted++;
        this._applying = apply;
      }
      return this._applying;
    },

    async sendChanges(action) {
      const currentIds = new Set(this.modules.map((m) => m.id));
      const changedIds = Object.keys(this.changes);
      const allIds = [...new Set([...changedIds, ...Object.keys(this.drafts), ...this.serverPendingIds, ...this.descendantPendingIds])]
        .filter((id) => currentIds.has(id));

      if (!allIds.length) {
        this.changes = {};
        this.serverPendingIds = [];
        this.descendantPendingIds = [];
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

      for (const id of succeeded) {
        if (action === "publish") this.markPublished(id);
        else delete this.changes[id];
      }
      this.serverPendingIds = this.serverPendingIds.filter(
        (id) => !succeeded.includes(id),
      );
      this.descendantPendingIds = this.descendantPendingIds.filter(
        (id) => !succeeded.includes(id),
      );
      for (const id of succeeded) {
        this.$events.emit("modules.published", { parent: this.pageUrl(id) });
      }

      if (lockFailed.length > 0) {
        this.$panel.notification.error(this.$t("modules.lock.applyFailed"));
      }
      for (const { id, reason } of otherFailed) {
        const name = this.modules.find((m) => m.id === id)?.moduleName || id;
        // notification.error keeps a message only from a string or Error
        this.$panel.notification.error(
          `${name}: ${reason?.message || this.$t("error")}`,
        );
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

        if (
          Object.keys(this.changes).length === 0 &&
          this.serverPendingIds.length === 0 &&
          this.descendantPendingIds.length === 0
        ) {
          this.undirtyParent();
        }
      }
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
    // Never persisted: keepDirtyMarkersOutOfPayloads() strips it.
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
      const owner = this.modules.find((m) => this.isParentApi(parentApi, this.pageUrl(m.id)));
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
      if (this.expanded[module.id]) {
        this.expanded[module.id] = false;
        this.saveCollapsedState();
        return;
      }
      if (module.hasFields && !this.fieldData[module.id]) {
        await this.loadFieldsBatch([module]);
      }
      this.expanded[module.id] = true;
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
    async revealModule(id) {
      const module = this.modules.find((m) => m.id === id);
      if (!module) return;
      if (!this.expanded[id]) await this.toggle(module);
      await this.$nextTick();
      const el = this.$el.querySelector(`[data-module-id="${id}"]`);
      if (!el) return;
      const rect = el.getBoundingClientRect();
      const headerHeight = parseFloat(getComputedStyle(el).scrollMarginBlockStart) || 0;
      const inView = rect.top >= headerHeight && rect.bottom <= window.innerHeight;
      if (!inView) el.scrollIntoView({ block: "center", behavior: "smooth" });
      await this.waitForDialogClose();
      this.$el
        .querySelector(`[data-module-id="${id}"]`)
        ?.focus({ preventScroll: true });
    },
    async waitForDialogClose() {
      if (!this.$panel.dialog.isOpen) return;
      await new Promise((resolve) => {
        const unwatch = this.$watch(
          () => this.$panel.dialog.isOpen,
          (open) => {
            if (!open) {
              unwatch();
              resolve();
            }
          },
        );
      });
      await this.$nextTick();
    },
    onClickOutside(e) {
      const clickedModule = e.target.closest(".k-module");
      if (clickedModule && this.$el.contains(clickedModule)) return;
      if (e.target.closest(".k-dialog, .k-drawer")) return;
      this.selectedModule = null;
    },

    // Content events use "/site" and "/pages/…", module ids "pages/…"
    isParentApi(api, against = this.parent) {
      return api?.replace(/^\//, "") === against.replace(/^\//, "");
    },
    isDescendantApi(api) {
      const own = this.parent.replace(/^\//, "");
      const candidate = api?.replace(/^\//, "");
      if (!candidate) return false;
      return own === candidate || own.startsWith(candidate + "+");
    },
    handleError(e) {
      if (e?.details?.isLocked) {
        // Reload like core's lock alert: brings in the lock prop, so
        // the form dims instead of silently reverting the edit
        this.$panel.view.reload();
        return;
      }
      // notification.error unwraps Error/RequestError objects itself
      this.$panel.notification.error(e || this.$t("error"));
    },
    encodeId(id) {
      return id.replace(/\//g, "+");
    },
    // Kirby's preview view only reloads on content and model events. The
    // field's own api matches no view, so nothing else reacts to it.
    notifyPreview() {
      this.$panel.content.emit("save", {}, { api: this.fieldUrl });
    },

    pageUrl(moduleId) {
      return "pages/" + this.encodeId(moduleId);
    },

    eventPayload(module) {
      return {
        field: this.name,
        parent: this.parent,
        id: module.id,
        api: this.pageUrl(module.id),
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
