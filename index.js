(function() {
  "use strict";
  function normalizeComponent(scriptExports, render, staticRenderFns, functionalTemplate, injectStyles, scopeId, moduleIdentifier, shadowMode) {
    var options = typeof scriptExports === "function" ? scriptExports.options : scriptExports;
    if (render) {
      options.render = render;
      options.staticRenderFns = staticRenderFns;
      options._compiled = true;
    }
    if (scopeId) {
      options._scopeId = "data-v-" + scopeId;
    }
    return {
      exports: scriptExports,
      options
    };
  }
  const _sfc_main$2 = {
    // All state comes from props, all actions emit events
    props: {
      module: Object,
      expanded: Boolean,
      loading: Boolean,
      selected: Boolean,
      values: Object,
      pageUrl: String,
      hasError: Boolean
    },
    data() {
      return {
        currentTab: null
      };
    },
    computed: {
      isDraft() {
        return this.module.status === "draft";
      },
      // Locked by another, no update permission or translate: false
      disabled() {
        return this.module.lock && this.module.lock.isLocked || !(this.module.permissions && this.module.permissions.update);
      },
      // Gate rendering until field values are loaded
      contentReady() {
        if (!this.module.hasFields) return true;
        return !!this.values && Object.keys(this.values).length > 0;
      },
      activeTab() {
        return this.currentTab || this.module.tabs[0] && this.module.tabs[0].name;
      },
      // Strip link prop that k-drawer-tabs doesn't need
      tabs() {
        return this.module.tabs.map(({ link, ...tab }) => tab);
      },
      // --- Toolbar buttons (primary) + dots dropdown (full action set) ---
      toolbar() {
        return [
          {
            icon: "edit",
            title: this.$t("edit"),
            click: () => this.$go(this.module.link)
          },
          ...this.module.previewUrl ? [{
            icon: "open",
            title: this.$t("preview"),
            link: this.module.previewUrl,
            target: "_blank"
          }] : [],
          {
            icon: "add",
            title: this.$t("modules.addBelow"),
            click: () => this.$emit("add", 1)
          },
          {
            icon: "trash",
            title: this.$t("delete"),
            click: () => this.$emit("remove")
          },
          // Sort handle: drag target + keyboard ArrowUp/ArrowDown
          {
            icon: "sort",
            title: this.$t("sort"),
            class: "k-sort-handle",
            key: (e) => {
              if (e.key === "ArrowUp") {
                e.preventDefault();
                this.$emit("sort", -1);
              }
              if (e.key === "ArrowDown") {
                e.preventDefault();
                this.$emit("sort", 1);
              }
            }
          },
          {
            icon: "dots",
            dropdown: [
              {
                icon: "edit",
                label: this.$t("edit"),
                click: () => this.$go(this.module.link)
              },
              {
                icon: this.isDraft ? "preview" : "hidden",
                label: this.isDraft ? this.$t("publish") : this.$t("modules.unpublish"),
                click: () => this.$emit("toggle-visibility")
              },
              ...this.module.previewUrl ? [{
                icon: "open",
                label: this.$t("preview"),
                link: this.module.previewUrl,
                target: "_blank"
              }] : [],
              "-",
              {
                icon: "template",
                label: this.$t("field.blocks.changeType"),
                click: () => this.$emit("change-type")
              },
              {
                icon: "hash",
                label: this.$t("modules.changeAnchor"),
                click: () => this.$emit("change-slug")
              },
              {
                icon: "copy",
                label: this.$t("duplicate"),
                click: () => this.$emit("duplicate")
              },
              "-",
              {
                icon: this.expanded ? "collapse" : "expand",
                label: this.expanded ? this.$t("collapse") : this.$t("expand"),
                click: () => this.$emit("toggle")
              },
              "-",
              {
                icon: "add-module-above",
                label: this.$t("modules.addAbove"),
                click: () => this.$emit("add", 0)
              },
              {
                icon: "add-module-below",
                label: this.$t("modules.addBelow"),
                click: () => this.$emit("add", 1)
              },
              "-",
              {
                icon: "trash",
                label: this.$t("delete"),
                click: () => this.$emit("remove")
              }
            ]
          }
        ];
      }
    },
    methods: {
      switchTab(tabName) {
        this.currentTab = tabName;
      }
    }
  };
  var _sfc_render$2 = function render() {
    var _vm = this, _c = _vm._self._c;
    return _c("div", { staticClass: "k-module", attrs: { "data-module-id": _vm.module.id, "data-status": _vm.module.status, "data-selected": _vm.selected, "data-disabled": _vm.disabled, "tabindex": _vm.disabled ? null : 0 }, on: { "focusin": function($event) {
      $event.stopPropagation();
      return _vm.$emit("select");
    } } }, [_c("div", { staticClass: "k-module-body", attrs: { "data-collapsed": !_vm.expanded } }, [_c("header", { staticClass: "k-module-header" }, [_c("div", { staticClass: "k-module-title" }, [_c("button", { staticClass: "k-module-toggle", on: { "click": function($event) {
      return _vm.$emit("toggle");
    } } }, [_vm.loading ? _c("k-icon", { attrs: { "type": "loader" } }) : _c("span", { staticClass: "k-module-icon" }, [_c("k-icon", { attrs: { "type": _vm.module.icon } }), _c("k-icon", { attrs: { "type": _vm.expanded ? "angle-up" : "angle-down" } })], 1)], 1), _c("span", { staticClass: "k-module-name" }, [_vm._v(_vm._s(_vm.module.moduleName))]), _c("button", { staticClass: "k-module-anchor", on: { "click": function($event) {
      return _vm.$emit("change-slug");
    } } }, [_vm._v(" #" + _vm._s(_vm.module.slug) + " ")])]), _c("k-drawer-tabs", { staticClass: "k-module-tabs", attrs: { "tab": _vm.activeTab, "tabs": _vm.tabs }, on: { "open": _vm.switchTab } }), _c("button", { staticClass: "k-module-status", attrs: { "data-status": _vm.module.status }, on: { "click": function($event) {
      $event.stopPropagation();
      return _vm.$emit("toggle-visibility");
    } } }, [_c("span", [_vm._v(_vm._s(_vm.isDraft ? _vm.$t("page.status.draft") : _vm.$t("page.status.listed")))]), _c("k-icon", { attrs: { "type": _vm.isDraft ? "hidden" : "preview" } })], 1)], 1), _vm.contentReady ? _c("div", { staticClass: "k-module-content" }, _vm._l(_vm.module.tabs, function(tab) {
      return _c("k-sections", { directives: [{ name: "show", rawName: "v-show", value: _vm.expanded && _vm.activeTab === tab.name, expression: "expanded && activeTab === tab.name" }], key: tab.name, attrs: { "parent": _vm.pageUrl, "tab": tab, "content": _vm.values }, on: { "input": function($event) {
        return _vm.$emit("input", $event);
      } } });
    }), 1) : _vm._e(), _vm.hasError ? _c("k-empty", { staticClass: "k-module-error", attrs: { "icon": "alert", "layout": "cardlets" } }, [_vm._v(" " + _vm._s(_vm.$t("error")) + " ")]) : _vm._e()], 1), _vm.selected && !_vm.disabled ? _c("k-toolbar", { staticClass: "k-module-toolbar", attrs: { "buttons": _vm.toolbar, "data-inline": "true" }, nativeOn: { "mousedown": function($event) {
      $event.preventDefault();
    } } }) : _vm._e()], 1);
  };
  var _sfc_staticRenderFns$2 = [];
  _sfc_render$2._withStripped = true;
  var __component__$2 = /* @__PURE__ */ normalizeComponent(
    _sfc_main$2,
    _sfc_render$2,
    _sfc_staticRenderFns$2,
    false,
    null,
    null
  );
  __component__$2.options.__file = "/Users/thguenther/Work/Repositories/kirby-modules/src/components/ModuleCard.vue";
  const ModuleCard = __component__$2.exports;
  const _sfc_main$1 = {
    components: {
      "k-module-card": ModuleCard
    },
    // ---------------------------------------------------------------
    // Props from Kirby's section system
    // ---------------------------------------------------------------
    props: {
      name: String,
      parent: String,
      timestamp: Number
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
        dragOptions: { handle: ".k-sort-handle" }
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
          { icon: "cog", click: () => {
            var _a;
            return (_a = this.$refs.options) == null ? void 0 : _a.toggle();
          } }
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
            disabled: this.isFullyExpanded
          },
          {
            text: this.$t("modules.collapseAll"),
            icon: "collapse",
            click: () => this.collapseAll(),
            disabled: this.isFullyCollapsed
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
                }
              }
            }),
            disabled: !this.modules.length || !this.link
          }
        ];
      },
      isFullyExpanded() {
        return this.modules.length > 0 && this.modules.every((m) => this.expanded[m.id]);
      },
      isFullyCollapsed() {
        return this.modules.length > 0 && this.modules.every((m) => !this.expanded[m.id]);
      }
    },
    // ---------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------
    watch: {
      timestamp() {
        this.fetch();
      }
    },
    created() {
      const init = this.parent !== "site" ? this.$api.post(this.sectionUrl + "/create-container") : Promise.resolve();
      init.then(() => this.fetch());
      this._onPublish = ({ api }) => {
        if (api === this.parent) this.applyChanges("publish");
      };
      this._onDiscard = ({ api }) => {
        if (api === this.parent) this.applyChanges("discard");
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
              this.$api.post(this.pageUrl(module.id) + "/changes/discard").catch(() => {
              });
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
        const needsLoad = module.hasFields && (!this.fieldData[module.id] || module.hasPendingChanges);
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
        this.serverPendingIds = this.modules.filter((m) => m.hasPendingChanges && !this.changes[m.id]).map((m) => m.id);
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
          const ids = this.modules.map((m) => m.id).filter((id) => id !== newModule.id);
          if (this.pendingInsertPosition >= 0) {
            ids.splice(this.pendingInsertPosition, 0, newModule.id);
          } else {
            ids.push(newModule.id);
          }
          this.modules = ids.map(
            (id) => this.modules.find((m) => m.id === id)
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
            this.sectionUrl + "/fields/" + this.encodeId(module.id)
          );
          this.$set(this.fieldData, module.id, {
            values: response.values,
            original: JSON.stringify(response.values)
            // Snapshot for revert detection
          });
        } catch (e) {
          this.handleError(e);
          this.$set(this.fieldData, module.id, { error: true });
        }
      },
      // --- Module actions --------------------------------------------------
      add(position = -1) {
        this.pendingInsertPosition = position;
        this.$dialog("modules/create", {
          query: {
            parent: this.link || this.parent,
            view: this.parent,
            section: this.name
          }
        });
      },
      addAt(module, offset) {
        const index = this.modules.findIndex((m) => m.id === module.id);
        this.add(index + offset);
      },
      async duplicate(module) {
        try {
          await this.$api.post(
            this.sectionUrl + "/duplicate/" + this.encodeId(module.id)
          );
          this.pendingInsertPosition = -1;
          this.fetch();
        } catch (e) {
          this.handleError(e);
        }
      },
      changeType(module) {
        this.$dialog("modules/change-type", {
          query: { page: this.encodeId(module.id) }
        });
      },
      changeSlug(module) {
        this.$dialog("modules/change-slug", {
          query: { page: this.encodeId(module.id) }
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
            this.sectionUrl + "/toggle-visibility/" + this.encodeId(module.id)
          );
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
            redirect: this.parent
          }
        });
      },
      // --- Field change tracking -------------------------------------------
      // Modules are child pages with their own content files.
      // Changes are saved to each module's _changes version immediately,
      // then published/discarded when the parent page is saved/discarded.
      async onInput(module, values) {
        const data = this.fieldData[module.id];
        const unchanged = (data == null ? void 0 : data.original) && JSON.stringify(values) === data.original;
        if (unchanged) {
          this.$delete(this.changes, module.id);
          this.$api.post(this.pageUrl(module.id) + "/changes/discard").catch(() => {
          });
        } else {
          this.$set(this.changes, module.id, values);
          try {
            await this.$api.post(this.pageUrl(module.id) + "/changes/save", values);
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
          const allIds = [.../* @__PURE__ */ new Set([...changedIds, ...this.serverPendingIds])].filter((id) => currentIds.has(id));
          if (!allIds.length) {
            this.changes = {};
            this.serverPendingIds = [];
            this.undirtyParent();
            return;
          }
          await Promise.all(
            allIds.map(
              (moduleId) => this.$api.post(this.pageUrl(moduleId) + "/changes/" + action).catch(() => {
              })
            )
          );
          this.changes = {};
          this.serverPendingIds = [];
          await Promise.all(
            this.modules.filter((m) => allIds.includes(m.id) && this.expanded[m.id] && m.hasFields).map((m) => this.loadFields(m))
          );
        } finally {
          this._isApplying = false;
        }
      },
      currentValues(moduleId) {
        var _a;
        return this.changes[moduleId] || ((_a = this.fieldData[moduleId]) == null ? void 0 : _a.values) || {};
      },
      // Dirty/undirty the parent page's Save/Discard buttons.
      // Key is scoped per section name to support multiple modules sections.
      dirtyParent() {
        this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: String(Date.now()) });
      },
      undirtyParent() {
        this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: void 0 });
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
          (id) => !this.expanded[id]
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
          (m) => !this.expanded[m.id] && m.hasFields && !this.fieldData[m.id]
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
      handleError(e) {
        this.$panel.notification.error(e.message || this.$t("error"));
      },
      encodeId(id) {
        return id.replace(/\//g, "+");
      },
      pageUrl(moduleId) {
        return "pages/" + this.encodeId(moduleId);
      }
    }
  };
  var _sfc_render$1 = function render() {
    var _vm = this, _c = _vm._self._c;
    return _c("k-section", { attrs: { "headline": _vm.headline, "buttons": _vm.sectionButtons, "required": Boolean(_vm.min), "invalid": _vm.isInvalid } }, [_c("k-dropdown-content", { ref: "options", attrs: { "options": _vm.dropdownOptions, "align-x": "end" } }), _vm.isLoading ? _c("k-loader") : !_vm.modules.length ? _c("k-empty", { attrs: { "icon": "box" }, on: { "click": function($event) {
      return _vm.add();
    } } }, [_vm._v(" " + _vm._s(_vm.empty) + " ")]) : _c("k-draggable", { staticClass: "k-modules-list", attrs: { "list": _vm.modules, "options": _vm.dragOptions }, on: { "sort": _vm.onSort } }, _vm._l(_vm.modules, function(module) {
      return _c("k-module-card", { key: module.id, attrs: { "module": module, "expanded": _vm.expanded[module.id] === true, "loading": !!_vm.loadingModules[module.id], "selected": _vm.selectedModule === module.id, "values": _vm.currentValues(module.id), "page-url": _vm.pageUrl(module.id), "has-error": !!(_vm.fieldData[module.id] && _vm.fieldData[module.id].error) }, on: { "toggle": function($event) {
        return _vm.toggle(module);
      }, "toggle-visibility": function($event) {
        return _vm.toggleVisibility(module);
      }, "select": function($event) {
        return _vm.select(module);
      }, "input": function($event) {
        return _vm.onInput(module, $event);
      }, "add": function($event) {
        return _vm.addAt(module, $event);
      }, "remove": function($event) {
        return _vm.remove(module);
      }, "duplicate": function($event) {
        return _vm.duplicate(module);
      }, "change-type": function($event) {
        return _vm.changeType(module);
      }, "change-slug": function($event) {
        return _vm.changeSlug(module);
      }, "sort": function($event) {
        return _vm.sortModule(module, $event);
      } } });
    }), 1), !_vm.isLoading && _vm.modules.length && _vm.canAdd ? _c("footer", [_c("k-button", { attrs: { "icon": "add", "size": "xs", "variant": "filled" }, on: { "click": function($event) {
      return _vm.add();
    } } })], 1) : _vm._e()], 1);
  };
  var _sfc_staticRenderFns$1 = [];
  _sfc_render$1._withStripped = true;
  var __component__$1 = /* @__PURE__ */ normalizeComponent(
    _sfc_main$1,
    _sfc_render$1,
    _sfc_staticRenderFns$1,
    false,
    null,
    "7a85869b"
  );
  __component__$1.options.__file = "/Users/thguenther/Work/Repositories/kirby-modules/src/components/ModulesSection.vue";
  const ModulesSection = __component__$1.exports;
  const _sfc_main = {
    extends: "k-page-create-dialog"
  };
  var _sfc_render = function render() {
    var _vm = this, _c = _vm._self._c;
    return _c("k-form-dialog", _vm._b({ ref: "dialog", staticClass: "k-module-create-dialog", on: { "cancel": function($event) {
      return _vm.$emit("cancel");
    }, "submit": function($event) {
      return _vm.$emit("submit", _vm.value);
    } } }, "k-form-dialog", _vm.$props, false), [_vm.templates.length > 1 ? _c("k-select-field", { attrs: { "empty": false, "label": _vm.$t("modules.create.type"), "options": _vm.templates, "required": true, "value": _vm.template }, on: { "input": function($event) {
      return _vm.pick($event);
    } } }) : _vm._e(), _c("k-dialog-fields", { attrs: { "fields": _vm.fields, "value": _vm.value }, on: { "input": function($event) {
      return _vm.$emit("input", $event);
    }, "submit": function($event) {
      return _vm.$emit("submit", $event);
    } } })], 1);
  };
  var _sfc_staticRenderFns = [];
  _sfc_render._withStripped = true;
  var __component__ = /* @__PURE__ */ normalizeComponent(
    _sfc_main,
    _sfc_render,
    _sfc_staticRenderFns,
    false,
    null,
    "2c6ae430"
  );
  __component__.options.__file = "/Users/thguenther/Work/Repositories/kirby-modules/src/components/ModuleCreateDialog.vue";
  const ModuleCreateDialog = __component__.exports;
  panel.plugin("medienbaecker/modules", {
    components: {
      "k-modules-section": ModulesSection,
      "k-module-create-dialog": ModuleCreateDialog
    },
    icons: {
      "add-module-above": '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 9V5H13V9H17V11H13V15H11V11H7V9H11ZM12 20C6.47715 20 2 15.5228 2 10C2 4.47715 6.47715 0 12 0C17.5228 0 22 4.47715 22 10C22 15.5228 17.5228 20 12 20ZM12 18C16.4183 18 20 14.4183 20 10C20 5.58172 16.4183 2 12 2C7.58172 2 4 5.58172 4 10C4 14.4183 7.58172 18 12 18Z"/><path d="M21 23C21 22.4477 20.5523 22 20 22H4C3.44772 22 3 22.4477 3 23C3 23.5523 3.44772 24 4 24H5.00001H19H20C20.5523 24 21 23.5523 21 23Z"/></svg>',
      "add-module-below": '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 15V19H13V15H17V13H13V9H11V13H7V15H11ZM12 4C6.47715 4 2 8.4772 2 14C2 19.5229 6.47715 24 12 24C17.5228 24 22 19.5229 22 14C22 8.4772 17.5228 4 12 4ZM12 6C16.4183 6 20 9.5817 20 14C20 18.4183 16.4183 22 12 22C7.58172 22 4 18.4183 4 14C4 9.5817 7.58172 6 12 6Z"/><path d="M21 1C21 1.5523 20.5523 2 20 2H4C3.44772 2 3 1.5523 3 1C3 0.4477 3.44772 0 4 0H5.00001H19H20C20.5523 0 21 0.4477 21 1Z"/></svg>',
      hash: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M7.78428 14L8.2047 10H4V8H8.41491L8.94043 3H10.9514L10.4259 8H14.4149L14.9404 3H16.9514L16.4259 8H20V10H16.2157L15.7953 14H20V16H15.5851L15.0596 21H13.0486L13.5741 16H9.58509L9.05957 21H7.04855L7.57407 16H4V14H7.78428ZM9.7953 14H13.7843L14.2047 10H10.2157L9.7953 14Z"></path></svg>',
      modules: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 5H20V3H4V5ZM20 9H4V7H20V9ZM3 11H10V13H14V11H21V20C21 20.5523 20.5523 21 20 21H4C3.44772 21 3 20.5523 3 20V11ZM16 13V15H8V13H5V19H19V13H16Z"></path></svg>'
    }
  });
})();
