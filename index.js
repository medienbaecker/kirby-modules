import * as __kirbyModulesVue from "vue";
(() => {
const { Fragment: e, createBlock: t, createCommentVNode: n, createElementBlock: r, createElementVNode: i, createTextVNode: a, createVNode: o, mergeProps: s, normalizeStyle: c, openBlock: l, renderList: u, resolveComponent: d, toDisplayString: f, vShow: p, withCtx: m, withDirectives: h, withModifiers: g } = __kirbyModulesVue;

//#region \0plugin-vue:export-helper
var _ = (e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
}, v = {
	emits: [
		"toggle",
		"toggle-visibility",
		"select",
		"input",
		"add",
		"remove",
		"duplicate",
		"change-type",
		"change-slug",
		"sort"
	],
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
			currentTab: null,
			sideWidth: 0,
			contentRendered: !1
		};
	},
	mounted() {
		let e = this.$el.querySelector(".k-module-header");
		this.sideObserver = new ResizeObserver(() => {
			let t = Math.max(this.$refs.title.offsetWidth, this.$refs.visibility.offsetWidth);
			this.sideWidth = 2 * t + 50 <= e.offsetWidth ? t : 0;
		}), this.sideObserver.observe(this.$refs.title), this.sideObserver.observe(this.$refs.visibility), this.sideObserver.observe(e), this.trackContentRender();
	},
	beforeUnmount() {
		this.sideObserver?.disconnect(), this.contentObserver?.disconnect(), clearTimeout(this.contentTimeout);
	},
	watch: { contentReady(e) {
		e ? this.trackContentRender() : this.contentRendered = !1;
	} },
	computed: {
		permissions() {
			return this.module.permissions || {};
		},
		disabled() {
			return !this.permissions.update;
		},
		contentReady() {
			return this.module.hasFields ? !!this.values && Object.keys(this.values).length > 0 : !0;
		},
		isAwaitingContent() {
			return !this.expanded || !this.module.hasFields || this.module.hasTemplate === !1 ? !1 : this.contentReady && !this.contentRendered;
		},
		activeTab() {
			return this.currentTab || this.module.tabs[0] && this.module.tabs[0].name;
		},
		tabs() {
			return this.module.tabs.map(({ link: e, ...t }) => t);
		},
		toolbar() {
			let e = this.permissions;
			return [
				{
					icon: "edit",
					title: this.$t("edit"),
					click: () => this.$go(this.module.link)
				},
				...this.module.previewUrl ? [{
					icon: "open",
					title: this.$t("preview"),
					click: () => window.open(this.module.previewUrl, "_blank"),
					disabled: !e.preview
				}] : [],
				{
					icon: "add",
					title: this.$t("modules.addBelow"),
					click: () => this.$emit("add", 1)
				},
				{
					icon: "trash",
					title: this.$t("delete"),
					click: () => this.$emit("remove"),
					disabled: !e.delete
				},
				{
					icon: "sort",
					title: this.$t("sort"),
					class: "k-sort-handle",
					disabled: !e.sort,
					key: (e) => {
						e.key === "ArrowUp" && (e.preventDefault(), this.$emit("sort", -1)), e.key === "ArrowDown" && (e.preventDefault(), this.$emit("sort", 1));
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
							icon: this.module.hidden ? "preview" : "hidden",
							label: this.module.hidden ? this.$t("publish") : this.$t("modules.unpublish"),
							click: () => this.$emit("toggle-visibility"),
							disabled: !e.update
						},
						...this.module.previewUrl ? [{
							icon: "open",
							label: this.$t("preview"),
							link: this.module.previewUrl,
							target: "_blank",
							disabled: !e.preview
						}] : [],
						"-",
						{
							icon: "template",
							label: this.$t("modules.changeType"),
							click: () => this.$emit("change-type"),
							disabled: !e.changeTemplate
						},
						{
							icon: "hash",
							label: this.$t("modules.changeAnchor"),
							click: () => this.$emit("change-slug"),
							disabled: !e.changeSlug
						},
						{
							icon: "copy",
							label: this.$t("duplicate"),
							click: () => this.$emit("duplicate"),
							disabled: !e.duplicate
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
							click: () => this.$emit("remove"),
							disabled: !e.delete
						}
					]
				}
			];
		}
	},
	methods: {
		switchTab(e) {
			this.currentTab = e;
		},
		trackContentRender() {
			this.contentRendered || !this.contentReady || !this.module.hasFields || this.module.hasTemplate === !1 || this.$nextTick(() => {
				let e = this.$el?.querySelector(".k-module-content");
				if (!e) return;
				let t = () => {
					this.contentRendered = !0, this.contentObserver?.disconnect(), clearTimeout(this.contentTimeout);
				};
				if (e.querySelector(".k-section")) return t();
				this.contentObserver = new MutationObserver(() => {
					e.querySelector(".k-section") && t();
				}), this.contentObserver.observe(e, {
					childList: !0,
					subtree: !0
				}), this.contentTimeout = setTimeout(t, 5e3);
			});
		}
	}
}, y = [
	"data-module-id",
	"data-hidden",
	"data-selected",
	"data-disabled",
	"aria-label"
], b = ["data-collapsed"], x = {
	ref: "title",
	class: "k-module-title"
}, S = ["aria-expanded", "aria-label"], C = {
	key: 1,
	class: "k-module-icon"
}, w = ["innerHTML"], T = ["aria-label", "disabled"], E = { class: "k-module-anchor-text" }, D = [
	"data-hidden",
	"aria-label",
	"disabled"
], O = {
	key: 0,
	class: "k-module-content"
};
function k(s, _, v, k, A, j) {
	let M = d("k-icon"), N = d("k-drawer-tabs"), P = d("k-empty"), F = d("k-sections"), I = d("k-toolbar");
	return l(), r("div", {
		class: "k-module",
		"data-module-id": v.module.id,
		"data-hidden": v.module.hidden,
		"data-selected": v.selected,
		"data-disabled": j.disabled,
		tabindex: "-1",
		role: "group",
		"aria-label": s.$t("modules.singular") + " " + v.module.moduleName,
		onFocusin: _[5] ||= g((e) => s.$emit("select"), ["stop"])
	}, [i("div", {
		class: "k-module-body",
		"data-collapsed": !v.expanded || j.isAwaitingContent
	}, [
		i("header", {
			class: "k-module-header",
			style: c({ "--side-width": A.sideWidth + "px" })
		}, [
			i("div", x, [
				i("button", {
					class: "k-module-toggle",
					"aria-expanded": String(v.expanded),
					"aria-label": s.$t("modules.singular") + " " + v.module.moduleName,
					onClick: _[0] ||= (e) => s.$emit("toggle")
				}, [v.loading || j.isAwaitingContent ? (l(), t(M, {
					key: 0,
					type: "loader"
				})) : (l(), r("span", C, [v.module.icon ? (l(), t(M, {
					key: 0,
					type: v.module.icon
				}, null, 8, ["type"])) : n("v-if", !0), o(M, { type: v.expanded ? "angle-up" : "angle-down" }, null, 8, ["type"])]))], 8, S),
				i("span", {
					class: "k-module-name",
					innerHTML: v.module.moduleName
				}, null, 8, w),
				i("button", {
					class: "k-module-anchor",
					"aria-label": s.$t("modules.changeAnchor") + ": " + v.module.slug,
					disabled: !j.permissions.changeSlug,
					onClick: _[1] ||= (e) => s.$emit("change-slug")
				}, [i("span", E, " #" + f(v.module.slug), 1)], 8, T)
			], 512),
			o(N, {
				class: "k-module-tabs",
				tab: j.activeTab,
				tabs: j.tabs,
				onOpen: j.switchTab
			}, null, 8, [
				"tab",
				"tabs",
				"onOpen"
			]),
			i("button", {
				ref: "visibility",
				class: "k-module-visibility",
				"data-hidden": v.module.hidden,
				"aria-label": v.module.hidden ? s.$t("publish") : s.$t("modules.unpublish"),
				disabled: !j.permissions.update,
				onClick: _[2] ||= g((e) => s.$emit("toggle-visibility"), ["stop"])
			}, [i("span", null, f(v.module.hidden ? s.$t("modules.hidden") : s.$t("modules.visible")), 1), o(M, { type: v.module.hidden ? "hidden" : "preview" }, null, 8, ["type"])], 8, D)
		], 4),
		j.contentReady ? (l(), r("div", O, [v.module.hasTemplate === !1 ? h((l(), t(P, {
			key: 0,
			icon: "alert",
			layout: "cardlets"
		}, {
			default: m(() => [a(f(s.$t("modules.missingTemplate.info")), 1)]),
			_: 1
		}, 512)), [[p, v.expanded]]) : (l(!0), r(e, { key: 1 }, u(v.module.tabs, (e) => h((l(), t(F, {
			key: e.name,
			parent: v.pageUrl,
			tab: e,
			content: v.values,
			onInput: _[3] ||= (e) => s.$emit("input", e)
		}, null, 8, [
			"parent",
			"tab",
			"content"
		])), [[p, v.expanded && j.activeTab === e.name]])), 128))])) : n("v-if", !0),
		v.hasError ? (l(), t(P, {
			key: 1,
			icon: "alert",
			layout: "cardlets",
			class: "k-module-error"
		}, {
			default: m(() => [a(f(s.$t("error")), 1)]),
			_: 1
		})) : n("v-if", !0)
	], 8, b), v.selected ? (l(), t(I, {
		key: 0,
		buttons: j.toolbar,
		"data-inline": "true",
		class: "k-module-toolbar",
		onMousedown: _[4] ||= g(() => {}, ["prevent"])
	}, null, 8, ["buttons"])) : n("v-if", !0)], 40, y);
}
var A = /*#__PURE__*/ _(v, [["render", k]]);
//#endregion
//#region src/components/ModulesSection.vue
function j(e) {
	let t = e.content;
	if (!t || typeof t.request != "function" || t.request._stripsMarkers) return;
	let n = t.request.bind(t);
	t.request = (e, t = {}, r = {}) => {
		let i = Object.fromEntries(Object.entries(t).filter(([e]) => !e.startsWith("_modulesChanged_")));
		return n(e, i, r);
	}, t.request._stripsMarkers = !0;
}
var M = {
	components: { "k-module-card": A },
	props: {
		name: String,
		parent: String,
		timestamp: Number,
		lock: Object
	},
	data() {
		return {
			headline: "",
			modules: [],
			empty: "",
			link: null,
			layout: null,
			canAdd: !0,
			min: null,
			max: null,
			expanded: {},
			fieldData: {},
			changes: {},
			serverPendingIds: [],
			loadingModules: {},
			isLoading: !0,
			selectedModule: null,
			pendingInsertPosition: null,
			pendingFocusInput: !1,
			dragOptions: { handle: ".k-sort-handle" }
		};
	},
	computed: {
		sectionUrl() {
			return this.parent + "/sections/" + this.name;
		},
		isInvalid() {
			return !!(this.min && this.modules.length < this.min || this.max && this.modules.length > this.max);
		},
		isHostLocked() {
			return this.lock?.isLocked === !0;
		},
		sectionButtons() {
			if (this.isHostLocked) return [];
			let e = [{
				icon: "cog",
				title: this.$t("options"),
				click: () => this.$refs.options?.toggle()
			}];
			return this.canAdd && e.push({
				icon: "add",
				text: this.$t("add"),
				click: () => this.add(),
				responsive: !0
			}), e;
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
						on: { submit: async () => {
							await this.$api.post(this.sectionUrl + "/deleteAll"), this.$panel.dialog.close(), this.fetch();
						} }
					}),
					disabled: !this.modules.length || !this.link
				}
			];
		},
		isFullyExpanded() {
			return this.modules.length > 0 && this.modules.every((e) => this.expanded[e.id]);
		},
		isFullyCollapsed() {
			return this.modules.length > 0 && this.modules.every((e) => !this.expanded[e.id]);
		}
	},
	watch: { timestamp() {
		this.fetch();
	} },
	created() {
		j(this.$panel), this._language = this.$panel.language?.code, this.headline = this.$t("modules.plural"), this.$api.post(this.sectionUrl + "/create-container").then(() => this.fetch()), this._onPublish = ({ api: e }) => {
			this.isParentApi(e) && this.applyChanges("publish");
		}, this._onDiscard = ({ api: e }) => {
			this.isParentApi(e) && this.applyChanges("discard");
		}, this.$events.on("content.publish", this._onPublish), this.$events.on("content.discard", this._onDiscard);
	},
	mounted() {
		document.addEventListener("mousedown", this.onClickOutside);
	},
	unmounted() {
		this.$events.off("content.publish", this._onPublish), this.$events.off("content.discard", this._onDiscard), document.removeEventListener("mousedown", this.onClickOutside);
	},
	methods: {
		async fetch() {
			try {
				let e = this.$panel.language?.code;
				this._language !== void 0 && this._language !== e && (this.fieldData = {}, this.changes = {}), this._language = e;
				let t = new Set(this.modules.map((e) => e.id)), n = new Map(this.modules.map((e) => [e.id, e.template])), r = await this.$api.get(this.sectionUrl);
				this.headline = r.options.headline, this.modules = r.data, this.empty = r.options.empty, this.link = r.options.link, this.layout = r.options.layout, this.canAdd = r.options.add, this.min = r.options.min, this.max = r.options.max;
				for (let e of this.modules) {
					let t = n.get(e.id);
					t && t !== e.template && (delete this.fieldData[e.id], delete this.changes[e.id], e.hasPendingChanges = !1, this.$api.post(this.pageUrl(e.id) + "/changes/discard", {}, { silent: !0 }).catch(() => {}));
				}
				let i = this.loadCollapsedState(), a = this.modules.filter((e) => this.restoreExpandState(e, i));
				this.reconcileState(), this.positionNewModule(t), this.loadFieldsBatch(a, !0);
			} catch (e) {
				this.handleError(e);
			} finally {
				this.isLoading = !1;
			}
		},
		restoreExpandState(e, t) {
			return t.includes(e.id) ? (this.expanded[e.id] = !1, !1) : e.hasFields && (!this.fieldData[e.id] || e.hasPendingChanges) ? !0 : (this.expanded[e.id] = !0, !1);
		},
		reconcileState() {
			let e = new Set(this.modules.map((e) => e.id)), t = [
				this.changes,
				this.fieldData,
				this.expanded,
				this.loadingModules
			];
			for (let n of t) for (let t of Object.keys(n)) e.has(t) || delete n[t];
			if (this.isHostLocked) {
				this.serverPendingIds = [], this.undirtyParent();
				return;
			}
			this.serverPendingIds = this.modules.filter((e) => e.hasPendingChanges && !this.changes[e.id] && !e.isLocked).map((e) => e.id), this.syncDirtyState();
		},
		positionNewModule(e) {
			if (this.pendingInsertPosition == null) return;
			let t = this.modules.find((t) => !e.has(t.id));
			if (t) {
				let e = this.modules.map((e) => e.id).filter((e) => e !== t.id);
				this.pendingInsertPosition >= 0 ? e.splice(this.pendingInsertPosition, 0, t.id) : e.push(t.id), this.modules = e.map((e) => this.modules.find((t) => t.id === e)), this.onSort(), this.$nextTick(() => {
					let e = this.$el.querySelector(`[data-module-id="${t.id}"]`);
					e && e.focus(), this.pendingFocusInput && e && (this.pendingFocusInput = !1, this.focusFirstInput(e));
				});
			}
			this.pendingInsertPosition = null;
		},
		focusFirstInput(e) {
			let t = [".k-module-content :where([autofocus], [data-autofocus])", ".k-module-content :where(input:not([type=hidden]), textarea, select, [contenteditable=true], .input-focus)"], n = Date.now() + 1e3, r = () => {
				for (let n of t) {
					let t = e.querySelector(n);
					if (t) {
						t.focus();
						return;
					}
				}
				Date.now() < n && setTimeout(r, 50);
			};
			r();
		},
		async loadFieldsBatch(e, t = !1) {
			for (let t of e) this.loadingModules[t.id] = !0;
			let n = [];
			for (let r = 0; r < e.length; r += 30) {
				let i = e.slice(r, r + 30);
				n.push(this.loadFieldsChunk(i).then(() => {
					for (let e of i) delete this.loadingModules[e.id], t && (this.expanded[e.id] = !0);
				}));
			}
			await Promise.all(n);
		},
		async loadFieldsChunk(e) {
			try {
				let t = await this.$api.post(this.sectionUrl + "/fields", { ids: e.map((e) => e.id) });
				for (let n of e) {
					let e = t[n.id];
					if (!e || e.error) {
						this.fieldData[n.id] = { error: !0 };
						continue;
					}
					this.fieldData[n.id] = {
						values: e.values,
						original: JSON.stringify(e.values)
					}, e.moduleName !== void 0 && (n.moduleName = e.moduleName);
				}
			} catch (t) {
				this.handleError(t);
				for (let t of e) this.fieldData[t.id] = { error: !0 };
			}
		},
		add(e = -1) {
			!this.canAdd || this.isHostLocked || (this.pendingInsertPosition = e, this.pendingFocusInput = !0, this.$dialog("modules/create", { query: {
				parent: this.link || this.parent,
				view: this.parent,
				section: this.name
			} }));
		},
		addAt(e, t) {
			let n = this.modules.findIndex((t) => t.id === e.id);
			this.add(n + t);
		},
		async duplicate(e) {
			try {
				this.pendingFocusInput = !1;
				let t = this.changes[e.id];
				t && await this.queueChanges(e.id, () => this.$api.post(this.pageUrl(e.id) + "/changes/save", t, { silent: !0 })), await this.$api.post(this.sectionUrl + "/duplicate/" + this.encodeId(e.id));
				let n = this.modules.findIndex((t) => t.id === e.id);
				this.pendingInsertPosition = n >= 0 ? n + 1 : -1, this.fetch();
			} catch (e) {
				this.handleError(e);
			}
		},
		async changeType(e) {
			await this.$panel.dialog.open("modules/change-type/" + this.encodeId(e.id)), this.$panel.dialog.addEventListener("success", () => {
				delete this.fieldData[e.id], delete this.changes[e.id], this.$panel.dialog.close(), this.fetch();
			});
		},
		changeSlug(e) {
			this.$dialog("modules/change-slug/" + this.encodeId(e.id));
		},
		async sortModule(e, t) {
			let n = this.modules.findIndex((t) => t.id === e.id), r = n + t;
			if (r < 0 || r >= this.modules.length) return;
			let i = [...this.modules];
			i.splice(n, 1), i.splice(r, 0, e), this.modules = i, this.onSort(), await this.$nextTick();
			let a = this.$el.querySelector(`[data-module-id="${e.id}"] .k-sort-handle`);
			a && a.focus();
		},
		async onSort() {
			let e = this.modules.map((e) => e.id);
			try {
				await this.$api.post(this.sectionUrl + "/sort", { ids: e });
			} catch (e) {
				this.handleError(e);
			}
			await this.fetch();
		},
		async toggleVisibility(e) {
			try {
				await this.$api.post(this.sectionUrl + "/toggle-visibility/" + this.encodeId(e.id)), await this.fetch(), this.$nextTick(() => {
					let t = this.$el.querySelector(`[data-module-id="${e.id}"]`);
					t && t.scrollIntoView({ block: "nearest" });
				});
			} catch (e) {
				this.handleError(e);
			}
		},
		remove(e) {
			this.$dialog(this.pageUrl(e.id) + "/delete", { query: { redirect: this.parent } });
		},
		queueChanges(e, t) {
			let n = this._changesChains ??= {}, r = (n[e] || Promise.resolve()).then(t, t);
			return n[e] = r.catch(() => {}), r;
		},
		async onInput(e, t) {
			let n = this.fieldData[e.id];
			if (n?.original && JSON.stringify(t) === n.original) delete this.changes[e.id], this.queueChanges(e.id, () => this.$api.post(this.pageUrl(e.id) + "/changes/discard", {}, { silent: !0 })).catch(() => {});
			else try {
				await this.queueChanges(e.id, () => this.$api.post(this.pageUrl(e.id) + "/changes/save", t, { silent: !0 })), this.changes[e.id] = t;
			} catch (e) {
				this.handleError(e);
				return;
			}
			this.syncDirtyState();
		},
		async applyChanges(e) {
			if (!this._isApplying) {
				this._isApplying = !0;
				try {
					let t = new Set(this.modules.map((e) => e.id)), n = Object.keys(this.changes), r = [.../* @__PURE__ */ new Set([...n, ...this.serverPendingIds])].filter((e) => t.has(e));
					if (!r.length) {
						this.changes = {}, this.serverPendingIds = [], this.undirtyParent();
						return;
					}
					let i = await Promise.allSettled(r.map((t) => this.queueChanges(t, () => this.$api.post(this.pageUrl(t) + "/changes/" + e)))), a = [], o = [], s = [];
					i.forEach((e, t) => {
						let n = r[t];
						e.status === "fulfilled" ? a.push(n) : e.reason?.details?.isLocked ? o.push(n) : s.push({
							id: n,
							reason: e.reason
						});
					});
					for (let e of a) delete this.changes[e];
					this.serverPendingIds = this.serverPendingIds.filter((e) => !a.includes(e)), o.length > 0 && this.$panel.notification.error(this.$t("modules.lock.applyFailed"));
					for (let { id: e, reason: t } of s) {
						let n = this.modules.find((t) => t.id === e)?.moduleName || e;
						this.$panel.notification.error({
							message: `${n}: ${t?.message || this.$t("error")}`,
							details: t?.details
						});
					}
					if (o.length > 0 || s.length > 0) {
						await this.fetch();
						let e = o[0] || s[0]?.id;
						e && this.revealModule(e);
					} else await this.loadFieldsBatch(this.modules.filter((e) => a.includes(e.id) && this.expanded[e.id] && e.hasFields)), Object.keys(this.changes).length === 0 && this.serverPendingIds.length === 0 && this.undirtyParent();
				} finally {
					this._isApplying = !1;
				}
			}
		},
		currentValues(e) {
			return this.changes[e] || this.fieldData[e]?.values || {};
		},
		dirtyParent() {
			this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: String(Date.now()) });
		},
		undirtyParent() {
			this.$panel.content.merge({ [`_modulesChanged_${this.name}`]: void 0 });
		},
		syncDirtyState() {
			this.serverPendingIds.length > 0 || Object.keys(this.changes).length > 0 ? this.dirtyParent() : this.undirtyParent();
		},
		async toggle(e) {
			if (this.expanded[e.id]) {
				this.expanded[e.id] = !1, this.saveCollapsedState();
				return;
			}
			e.hasFields && !this.fieldData[e.id] && await this.loadFieldsBatch([e]), this.expanded[e.id] = !0, this.saveCollapsedState();
		},
		saveCollapsedState() {
			let e = "kirby-modules:" + this.parent + ":" + this.name, t = Object.keys(this.expanded).filter((e) => !this.expanded[e]);
			localStorage.setItem(e, JSON.stringify(t));
		},
		loadCollapsedState() {
			let e = "kirby-modules:" + this.parent + ":" + this.name;
			try {
				let t = localStorage.getItem(e);
				return t ? JSON.parse(t) : [];
			} catch {
				return [];
			}
		},
		async expandAll() {
			let e = this.modules.filter((e) => !this.expanded[e.id] && e.hasFields && !this.fieldData[e.id]);
			await this.loadFieldsBatch(e);
			for (let e of this.modules) this.expanded[e.id] = !0;
			this.saveCollapsedState();
		},
		collapseAll() {
			for (let e of this.modules) this.expanded[e.id] = !1;
			this.saveCollapsedState();
		},
		select(e) {
			this.selectedModule = e.id;
		},
		async revealModule(e) {
			let t = this.modules.find((t) => t.id === e);
			if (!t) return;
			this.expanded[e] || await this.toggle(t), await this.$nextTick();
			let n = this.$el.querySelector(`[data-module-id="${e}"]`);
			n?.scrollIntoView({
				block: "nearest",
				behavior: "smooth"
			}), n?.focus();
		},
		onClickOutside(e) {
			let t = e.target.closest(".k-module");
			t && this.$el.contains(t) || e.target.closest(".k-dialog, .k-drawer") || (this.selectedModule = null);
		},
		isParentApi(e) {
			return e?.replace(/^\//, "") === this.parent.replace(/^\//, "");
		},
		handleError(e) {
			if (e?.details?.isLocked) {
				this.$panel.view.reload();
				return;
			}
			this.$panel.notification.error(e || this.$t("error"));
		},
		encodeId(e) {
			return e.replace(/\//g, "+");
		},
		pageUrl(e) {
			return "pages/" + this.encodeId(e);
		}
	}
}, N = { key: 3 };
function P(i, s, c, p, h, g) {
	let _ = d("k-dropdown"), v = d("k-icon"), y = d("k-empty"), b = d("k-module-card"), x = d("k-draggable"), S = d("k-button"), C = d("k-section");
	return l(), t(C, {
		class: "k-modules-section",
		headline: h.headline,
		buttons: g.sectionButtons,
		required: !!h.min,
		invalid: g.isInvalid
	}, {
		default: m(() => [
			o(_, {
				ref: "options",
				options: g.dropdownOptions,
				"align-x": "end"
			}, null, 8, ["options"]),
			h.isLoading ? (l(), t(v, {
				key: 0,
				type: "loader"
			})) : h.modules.length ? (l(), t(x, {
				key: 2,
				list: h.modules,
				options: h.dragOptions,
				onSort: g.onSort,
				class: "k-modules-list",
				"data-layout": h.layout
			}, {
				default: m(() => [(l(!0), r(e, null, u(h.modules, (e) => (l(), t(b, {
					key: e.id + ":" + e.template,
					module: e,
					expanded: h.expanded[e.id] === !0,
					loading: !!h.loadingModules[e.id],
					selected: h.selectedModule === e.id,
					values: g.currentValues(e.id),
					"page-url": g.pageUrl(e.id),
					"has-error": !!(h.fieldData[e.id] && h.fieldData[e.id].error),
					onToggle: (t) => g.toggle(e),
					onToggleVisibility: (t) => g.toggleVisibility(e),
					onSelect: (t) => g.select(e),
					onInput: (t) => g.onInput(e, t),
					onAdd: (t) => g.addAt(e, t),
					onRemove: (t) => g.remove(e),
					onDuplicate: (t) => g.duplicate(e),
					onChangeType: (t) => g.changeType(e),
					onChangeSlug: (t) => g.changeSlug(e),
					onSort: (t) => g.sortModule(e, t)
				}, null, 8, [
					"module",
					"expanded",
					"loading",
					"selected",
					"values",
					"page-url",
					"has-error",
					"onToggle",
					"onToggleVisibility",
					"onSelect",
					"onInput",
					"onAdd",
					"onRemove",
					"onDuplicate",
					"onChangeType",
					"onChangeSlug",
					"onSort"
				]))), 128))]),
				_: 1
			}, 8, [
				"list",
				"options",
				"onSort",
				"data-layout"
			])) : (l(), t(y, {
				key: 1,
				icon: "box",
				onClick: s[0] ||= (e) => g.add()
			}, {
				default: m(() => [a(f(h.empty), 1)]),
				_: 1
			})),
			!h.isLoading && h.modules.length && h.canAdd ? (l(), r("footer", N, [o(S, {
				icon: "add",
				size: "xs",
				variant: "filled",
				title: i.$t("add"),
				onClick: s[1] ||= (e) => g.add()
			}, null, 8, ["title"])])) : n("v-if", !0)
		]),
		_: 1
	}, 8, [
		"headline",
		"buttons",
		"required",
		"invalid"
	]);
}
var F = /*#__PURE__*/ _(M, [["render", P], ["__scopeId", "data-v-dce90d14"]]), I = {
	props: {
		types: {
			type: Array,
			default: () => []
		},
		selected: String
	},
	computed: {
		hasPreviews() {
			return this.types.some((e) => e.preview);
		},
		typeOptions() {
			return this.types.map((e) => ({
				value: e.name,
				text: e.title,
				disabled: e.disabled
			}));
		}
	},
	methods: { image(e) {
		return e.preview ? {
			src: e.preview,
			cover: !0,
			ratio: "16/9",
			back: "pattern"
		} : {
			icon: e.icon || "box",
			ratio: "16/9",
			back: "pattern",
			color: "var(--color-white)"
		};
	} }
}, L = { class: "k-module-type-field" }, R = { class: "k-field-header" }, z = { class: "k-label k-field-label" }, B = { class: "k-label-text" }, V = [
	"aria-current",
	"aria-label",
	"disabled",
	"onClick"
], H = { class: "k-module-type-label" };
function U(n, a, s, c, p, h) {
	let g = d("k-item-image"), _ = d("k-navigate"), v = d("k-select-field");
	return l(), r("div", L, [h.hasPreviews ? (l(), r(e, { key: 0 }, [i("header", R, [i("label", z, [i("span", B, f(n.$t("modules.create.type")), 1)])]), o(_, { class: "k-module-types" }, {
		default: m(() => [(l(!0), r(e, null, u(s.types, (e) => (l(), r("button", {
			key: e.name,
			type: "button",
			class: "k-module-type",
			"aria-current": e.name === s.selected,
			"aria-label": e.title,
			disabled: e.disabled,
			onClick: (t) => n.$emit("select", e.name)
		}, [o(g, {
			class: "k-module-type-image",
			image: h.image(e),
			layout: "cards"
		}, null, 8, ["image"]), i("span", H, f(e.title), 1)], 8, V))), 128))]),
		_: 1
	})], 64)) : (l(), t(v, {
		key: 1,
		label: n.$t("modules.create.type"),
		options: h.typeOptions,
		value: s.selected,
		empty: !1,
		required: !0,
		onInput: a[0] ||= (e) => n.$emit("select", e)
	}, null, 8, [
		"label",
		"options",
		"value"
	]))]);
}
var W = /*#__PURE__*/ _(I, [["render", U], ["__scopeId", "data-v-19887a1b"]]), G = { extends: "k-page-create-dialog" };
function K(e, r, i, a, c, u) {
	let f = d("k-module-type-grid"), p = d("k-dialog-fields"), h = d("k-form-dialog");
	return l(), t(h, s({
		ref: "dialog",
		size: "large"
	}, e.$props, {
		class: "k-module-create-dialog",
		onCancel: r[2] ||= (t) => e.$emit("cancel"),
		onSubmit: r[3] ||= (t) => e.$emit("submit", e.value)
	}), {
		default: m(() => [e.blueprints.length > 1 ? (l(), t(f, {
			key: 0,
			types: e.blueprints,
			selected: e.template,
			onSelect: e.pick
		}, null, 8, [
			"types",
			"selected",
			"onSelect"
		])) : n("v-if", !0), o(p, {
			fields: e.fields,
			value: e.value,
			onInput: r[0] ||= (t) => e.$emit("input", t),
			onSubmit: r[1] ||= (t) => e.$emit("submit", t)
		}, null, 8, ["fields", "value"])]),
		_: 1
	}, 16);
}
var q = /*#__PURE__*/ _(G, [["render", K], ["__scopeId", "data-v-b1e9fb28"]]), J = {
	extends: "k-form-dialog",
	props: { blueprints: {
		type: Array,
		default: () => []
	} },
	methods: { select(e) {
		this.$emit("input", {
			...this.value,
			template: e
		});
	} }
};
function Y(e, n, r, i, a, c) {
	let u = d("k-module-type-grid"), f = d("k-form-dialog");
	return l(), t(f, s({
		ref: "dialog",
		size: "large"
	}, e.$props, {
		class: "k-module-change-type-dialog",
		onCancel: n[0] ||= (t) => e.$emit("cancel"),
		onSubmit: n[1] ||= (t) => e.$emit("submit", e.value)
	}), {
		default: m(() => [o(u, {
			types: r.blueprints,
			selected: e.value.template,
			onSelect: c.select
		}, null, 8, [
			"types",
			"selected",
			"onSelect"
		])]),
		_: 1
	}, 16);
}
var X = /*#__PURE__*/ _(J, [["render", Y]]), Z = { props: {
	license: {
		type: Object,
		default: () => ({})
	},
	cancelButton: { default: !1 },
	submitButton: { default: !1 }
} }, Q = { class: "k-table" }, $ = { class: "k-modules-license-table" }, ee = { key: 0 }, te = { "data-mobile": "true" }, ne = {
	"data-mobile": "true",
	class: "k-text"
}, re = { key: 1 }, ie = { "data-mobile": "true" }, ae = { "data-mobile": "true" }, oe = { class: "k-modules-license-thanks" };
function se(e, a, s, c, u, p) {
	let h = d("k-button"), g = d("k-bar"), _ = d("k-dialog");
	return l(), t(_, {
		class: "k-modules-license-dialog",
		size: "large",
		"cancel-button": s.cancelButton,
		"submit-button": s.submitButton,
		visible: !0,
		onCancel: a[0] ||= (t) => e.$emit("cancel")
	}, {
		default: m(() => [
			o(g, { class: "k-modules-license-header" }, {
				default: m(() => [a[1] ||= i("h2", { class: "k-headline" }, " Kirby Modules ", -1), o(h, {
					text: e.$t("remove"),
					icon: "trash",
					size: "xs",
					variant: "filled",
					dialog: "modules/remove-license"
				}, null, 8, ["text"])]),
				_: 1
			}),
			i("div", Q, [i("table", $, [i("tbody", null, [s.license.code ? (l(), r("tr", ee, [i("th", te, f(e.$t("license.code")), 1), i("td", ne, [i("code", null, f(s.license.code), 1)])])) : n("v-if", !0), s.license.version ? (l(), r("tr", re, [i("th", ie, f(e.$t("version")), 1), i("td", ae, f(s.license.version), 1)])) : n("v-if", !0)])])]),
			i("p", oe, f(e.$t("modules.license.active.info")), 1)
		]),
		_: 1
	}, 8, ["cancel-button", "submit-button"]);
}
var ce = /*#__PURE__*/ _(Z, [["render", se]]);
//#endregion
//#region src/index.js
panel.plugin("medienbaecker/modules", {
	components: {
		"k-modules-section": F,
		"k-module-type-grid": W,
		"k-module-create-dialog": q,
		"k-module-change-type-dialog": X,
		"k-modules-license-dialog": ce
	},
	icons: {
		"add-module-above": "<svg width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M11 9V5H13V9H17V11H13V15H11V11H7V9H11ZM12 20C6.47715 20 2 15.5228 2 10C2 4.47715 6.47715 0 12 0C17.5228 0 22 4.47715 22 10C22 15.5228 17.5228 20 12 20ZM12 18C16.4183 18 20 14.4183 20 10C20 5.58172 16.4183 2 12 2C7.58172 2 4 5.58172 4 10C4 14.4183 7.58172 18 12 18Z\"/><path d=\"M21 23C21 22.4477 20.5523 22 20 22H4C3.44772 22 3 22.4477 3 23C3 23.5523 3.44772 24 4 24H5.00001H19H20C20.5523 24 21 23.5523 21 23Z\"/></svg>",
		"add-module-below": "<svg width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M11 15V19H13V15H17V13H13V9H11V13H7V15H11ZM12 4C6.47715 4 2 8.4772 2 14C2 19.5229 6.47715 24 12 24C17.5228 24 22 19.5229 22 14C22 8.4772 17.5228 4 12 4ZM12 6C16.4183 6 20 9.5817 20 14C20 18.4183 16.4183 22 12 22C7.58172 22 4 18.4183 4 14C4 9.5817 7.58172 6 12 6Z\"/><path d=\"M21 1C21 1.5523 20.5523 2 20 2H4C3.44772 2 3 1.5523 3 1C3 0.4477 3.44772 0 4 0H5.00001H19H20C20.5523 0 21 0.4477 21 1Z\"/></svg>",
		hash: "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"currentColor\"><path d=\"M7.78428 14L8.2047 10H4V8H8.41491L8.94043 3H10.9514L10.4259 8H14.4149L14.9404 3H16.9514L16.4259 8H20V10H16.2157L15.7953 14H20V16H15.5851L15.0596 21H13.0486L13.5741 16H9.58509L9.05957 21H7.04855L7.57407 16H4V14H7.78428ZM9.7953 14H13.7843L14.2047 10H10.2157L9.7953 14Z\"></path></svg>",
		modules: "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"currentColor\"><path d=\"M4 5H20V3H4V5ZM20 9H4V7H20V9ZM3 11H10V13H14V11H21V20C21 20.5523 20.5523 21 20 21H4C3.44772 21 3 20.5523 3 20V11ZM16 13V15H8V13H5V19H19V13H16Z\"></path></svg>"
	}
});
//#endregion

})();
