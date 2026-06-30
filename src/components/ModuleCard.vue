<template>
  <div class="k-module" :data-module-id="module.id" :data-hidden="module.hidden" :data-selected="selected"
    :data-disabled="disabled" tabindex="0" role="group" :aria-label="$t('modules.singular') + ' ' + module.moduleName"
    @focusin.stop="$emit('select')">
    <div class="k-module-body" :data-collapsed="!expanded || isAwaitingContent">
      <header class="k-module-header" :style="{ '--side-width': sideWidth + 'px' }">
        <div ref="title" class="k-module-title">
          <button class="k-module-toggle" :aria-expanded="String(expanded)"
            :aria-label="$t('modules.singular') + ' ' + module.moduleName" @click="$emit('toggle')">
            <k-icon v-if="loading || isAwaitingContent" type="loader" />
            <span v-else class="k-module-icon">
              <k-icon :type="module.icon" v-if="module.icon !== false" />
              <k-icon :type="expanded ? 'angle-up' : 'angle-down'" />
            </span>
          </button>
          <span class="k-module-name">{{ module.moduleName }}</span>
          <button class="k-module-anchor" :aria-label="$t('modules.changeAnchor') + ': ' + module.slug"
            :disabled="!permissions.changeSlug" @click="$emit('change-slug')">
            <span class="k-module-anchor-text">
              #{{ module.slug }}
            </span>
          </button>
        </div>
        <k-drawer-tabs class="k-module-tabs" :tab="activeTab" :tabs="tabs" @open="switchTab" />
        <button ref="visibility" class="k-module-visibility" :data-hidden="module.hidden"
          :aria-label="module.hidden ? $t('publish') : $t('modules.unpublish')" :disabled="!permissions.update"
          @click.stop="$emit('toggle-visibility')">
          <span>{{ module.hidden ? $t('modules.hidden') : $t('modules.visible') }}</span>
          <k-icon :type="module.hidden ? 'hidden' : 'preview'" />
        </button>
      </header>
      <div v-if="contentReady" class="k-module-content">
        <k-empty v-if="module.hasTemplate === false" v-show="expanded" icon="alert" layout="cardlets">
          {{ $t("modules.missingTemplate.info") }}
        </k-empty>
        <k-sections v-else v-for="tab in module.tabs" v-show="expanded && activeTab === tab.name" :key="tab.name"
          :parent="pageUrl" :tab="tab" :content="values" @input="$emit('input', $event)" />
      </div>
      <k-empty v-if="hasError" icon="alert" layout="cardlets" class="k-module-error">
        {{ $t("error") }}
      </k-empty>
    </div>

    <k-toolbar v-if="selected" :buttons="toolbar" data-inline="true" class="k-module-toolbar"
      @mousedown.native.prevent />
  </div>
</template>

<script>
export default {
  props: {
    module: Object,
    expanded: Boolean,
    loading: Boolean,
    selected: Boolean,
    values: Object,
    pageUrl: String,
    hasError: Boolean,
  },
  data() {
    return {
      currentTab: null,
      sideWidth: 0,
      contentRendered: false,
    };
  },
  mounted() {
    const header = this.$el.querySelector(".k-module-header");
    this.sideObserver = new ResizeObserver(() => {
      const max = Math.max(this.$refs.title.offsetWidth, this.$refs.visibility.offsetWidth);
      // Fall back to asymmetric (tabs centered between siblings) when the
      // symmetric layout wouldn't leave reasonable room for tabs.
      const minTabs = 50;
      this.sideWidth = (2 * max + minTabs <= header.offsetWidth) ? max : 0;
    });
    this.sideObserver.observe(this.$refs.title);
    this.sideObserver.observe(this.$refs.visibility);
    this.sideObserver.observe(header);

    this.trackContentRender();
  },
  beforeDestroy() {
    this.sideObserver?.disconnect();
    this.contentObserver?.disconnect();
    clearTimeout(this.contentTimeout);
  },
  watch: {
    contentReady(ready) {
      if (ready) this.trackContentRender();
      else this.contentRendered = false;
    },
  },
  computed: {
    permissions() {
      return this.module.permissions || {};
    },
    disabled() {
      return !this.permissions.update;
    },
    contentReady() {
      if (!this.module.hasFields) return true;
      return !!this.values && Object.keys(this.values).length > 0;
    },
    isAwaitingContent() {
      if (!this.expanded || !this.module.hasFields || this.module.hasTemplate === false) {
        return false;
      }
      return this.contentReady && !this.contentRendered;
    },
    activeTab() {
      return this.currentTab || (this.module.tabs[0] && this.module.tabs[0].name);
    },
    tabs() {
      return this.module.tabs.map(({ link, ...tab }) => tab);
    },

    toolbar() {
      const p = this.permissions;
      return [
        {
          icon: "edit",
          title: this.$t("edit"),
          click: () => this.$go(this.module.link),
        },
        ...(this.module.previewUrl ? [{
          icon: "open",
          title: this.$t("preview"),
          click: () => window.open(this.module.previewUrl, "_blank"),
          disabled: !p.preview,
        }] : []),
        {
          icon: "add",
          title: this.$t("modules.addBelow"),
          click: () => this.$emit("add", 1),
        },
        {
          icon: "trash",
          title: this.$t("delete"),
          click: () => this.$emit("remove"),
          disabled: !p.delete,
        },
        {
          icon: "sort",
          title: this.$t("sort"),
          class: "k-sort-handle",
          disabled: !p.sort,
          key: (e) => {
            if (e.key === "ArrowUp") { e.preventDefault(); this.$emit("sort", -1); }
            if (e.key === "ArrowDown") { e.preventDefault(); this.$emit("sort", 1); }
          },
        },
        {
          icon: "dots",
          dropdown: [
            {
              icon: "edit",
              label: this.$t("edit"),
              click: () => this.$go(this.module.link),
            },
            {
              icon: this.module.hidden ? "preview" : "hidden",
              label: this.module.hidden ? this.$t("publish") : this.$t("modules.unpublish"),
              click: () => this.$emit("toggle-visibility"),
              disabled: !p.update,
            },
            ...(this.module.previewUrl ? [{
              icon: "open",
              label: this.$t("preview"),
              link: this.module.previewUrl,
              target: "_blank",
              disabled: !p.preview,
            }] : []),
            "-",
            {
              icon: "template",
              label: this.$t("modules.changeType"),
              click: () => this.$emit("change-type"),
              disabled: !p.changeTemplate,
            },
            {
              icon: "hash",
              label: this.$t("modules.changeAnchor"),
              click: () => this.$emit("change-slug"),
              disabled: !p.changeSlug,
            },
            {
              icon: "copy",
              label: this.$t("duplicate"),
              click: () => this.$emit("duplicate"),
              disabled: !p.duplicate,
            },
            "-",
            {
              icon: this.expanded ? "collapse" : "expand",
              label: this.expanded ? this.$t("collapse") : this.$t("expand"),
              click: () => this.$emit("toggle"),
            },
            "-",
            {
              icon: "add-module-above",
              label: this.$t("modules.addAbove"),
              click: () => this.$emit("add", 0),
            },
            {
              icon: "add-module-below",
              label: this.$t("modules.addBelow"),
              click: () => this.$emit("add", 1),
            },
            "-",
            {
              icon: "trash",
              label: this.$t("delete"),
              click: () => this.$emit("remove"),
              disabled: !p.delete,
            },
          ],
        },
      ];
    },
  },
  methods: {
    switchTab(tabName) {
      this.currentTab = tabName;
    },
    // A FieldsSection renders nothing until its request resolves; wait for the
    // mounted .k-section so the card reveals fields, not an empty padding box.
    trackContentRender() {
      if (this.contentRendered) return;
      if (!this.contentReady || !this.module.hasFields || this.module.hasTemplate === false) {
        return;
      }
      this.$nextTick(() => {
        const content = this.$el?.querySelector(".k-module-content");
        if (!content) return;

        const done = () => {
          this.contentRendered = true;
          this.contentObserver?.disconnect();
          clearTimeout(this.contentTimeout);
        };

        if (content.querySelector(".k-section")) return done();

        this.contentObserver = new MutationObserver(() => {
          if (content.querySelector(".k-section")) done();
        });
        this.contentObserver.observe(content, { childList: true, subtree: true });
        // Reveal anyway if a section errors and never renders.
        this.contentTimeout = setTimeout(done, 5000);
      });
    },
  },
};
</script>

<style>
.k-module {
  --module-color-back: light-dark(var(--color-white), var(--color-gray-850));

  container: module / inline-size;
  position: relative;
  background: var(--module-color-back);
  box-shadow: var(--shadow);
  border-radius: var(--rounded);
  scroll-margin-block-start: var(--header-sticky-offset);

  &[data-hidden="true"] {
    --module-color-back: repeating-linear-gradient(45deg,
        light-dark(var(--color-white), var(--color-gray-850)),
        light-dark(var(--color-white), var(--color-gray-850)) 1rem,
        light-dark(color-mix(in srgb, var(--color-white), transparent 50%), color-mix(in srgb, var(--color-gray-850), transparent 50%)) 1rem,
        light-dark(color-mix(in srgb, var(--color-white), transparent 50%), color-mix(in srgb, var(--color-gray-850), transparent 50%)) 2rem);
    box-shadow: none;
  }

  &[data-selected="true"] {
    outline: var(--outline);
  }

  &.k-sortable-ghost {
    outline: 2px solid var(--color-focus);
    box-shadow: rgba(17, 17, 17, 0.25) 0 5px 10px;
    cursor: grabbing;
  }
}

.k-module[data-disabled="true"] .k-fields-section {
  opacity: 0.2;
  pointer-events: none;
}

.k-module:is(.k-sortable-ghost, .k-sortable-fallback) .k-module-body {
  max-height: var(--drawer-header-height);
  overflow: clip;
}

.k-module-body:not([data-collapsed="true"]) {
  padding-block-end: var(--spacing-3);
}

.k-module-body[data-collapsed="true"] .k-module-content,
.k-module-body[data-collapsed="true"] .k-drawer-tabs {
  display: none;
}

.k-module-header {
  display: grid;
  grid-template-columns:
    [title] minmax(var(--side-width, 0px), auto) [tabs] minmax(0, 1fr) [visibility] minmax(var(--side-width, 0px), auto);
  gap: var(--spacing-2);
  height: var(--drawer-header-height);
}

.k-module-title {
  grid-column: title;
  display: flex;
  justify-self: start;
  gap: var(--spacing-2);
  z-index: 1;
}

.k-module-toggle {
  display: flex;
  align-items: center;
  border-radius: var(--rounded);
  padding-inline: var(--spacing-3);
  color: var(--color-text-dimmed);
  z-index: 1;

  &:hover,
  &:focus-visible,
  &:not(:has(.k-icon:nth-child(2))) {
    --show-arrow: true;
    color: var(--color-text);
  }
}

.k-module-icon {
  display: grid;
}

.k-module-icon > * {
  grid-area: 1 / 1;
}

.k-module-icon > :last-child {
  visibility: hidden;
}

@container style(--show-arrow: true) {
  .k-module-icon > :first-child {
    visibility: hidden;
  }

  .k-module-icon > :last-child {
    visibility: visible;
  }
}

.k-module-name {
  align-self: center;
  margin-inline: calc(var(--spacing-3)* -1);
}

.k-module-anchor {
  display: flex;
  align-items: center;
  font-size: var(--text-xs);
  color: var(--color-text-dimmed);
  border-radius: var(--rounded);
  padding-inline: var(--spacing-3);
  z-index: 1;

  &:not(:disabled):hover,
  &:not(:disabled):focus-visible {
    color: var(--color-text);
  }
}

.k-module-anchor-text {
  white-space: nowrap;
  max-inline-size: 7rem;
  overflow-x: clip;
  text-overflow: ellipsis;
}

@container module (max-width: 600px) {
  .k-module-anchor {
    display: none;
  }
}

.k-module-tabs {
  grid-column: tabs;
  min-width: 0;

  /* Double class needed for specificity over Kirby's drawer-tabs default. */
  &.k-tabs {
    justify-content: center;
  }
}

.k-module-visibility {
  grid-column: visibility;
  z-index: 2;
  justify-self: end;

  display: flex;
  align-items: center;
  gap: var(--spacing-1);
  padding-inline: var(--spacing-3);

  font-size: var(--text-xs);
  color: var(--color-text-dimmed);
  border-radius: var(--rounded);

  &:not(:disabled):hover,
  &:not(:disabled):focus-visible {
    color: var(--color-text);
  }
}

.k-module-content {
  background-color: var(--panel-color-back);
  border-radius: var(--rounded-sm);
  padding: var(--spacing-6) var(--spacing-6) var(--spacing-8);
  margin: 0 var(--spacing-3);
  container: column / inline-size;
}

.k-module-toolbar {
  --toolbar-size: 30px;
  display: none;
  position: absolute;
  z-index: 3;
  inset-block-start: 0;
  inset-inline-end: var(--spacing-3);
  margin-block-start: calc(-1.75rem + 2px);
  box-shadow: var(--shadow-xl);
  border: 1px solid light-dark(var(--color-border), var(--color-gray-900));
}

.k-module[data-selected="true"] > .k-module-toolbar {
  display: flex;
}

.k-module-toolbar > .k-button:not(:last-of-type) {
  border-inline-end: 1px solid var(--toolbar-border);
}
</style>
