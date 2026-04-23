<template>
  <div class="k-module" :data-module-id="module.id" :data-status="module.status" :data-selected="selected"
    :data-disabled="disabled" :tabindex="disabled ? null : 0" role="group" :aria-label="$t('modules.singular') + ' ' + module.moduleName"
    @focusin.stop="$emit('select')">
    <div class="k-module-body" :data-collapsed="!expanded">
      <header class="k-module-header">
        <div class="k-module-title">
          <button class="k-module-toggle" :aria-expanded="String(expanded)"
            :aria-label="$t('modules.singular') + ' ' + module.moduleName"
            @click="$emit('toggle')">
            <k-icon v-if="loading" type="loader" />
            <span v-else class="k-module-icon">
              <k-icon :type="module.icon" />
              <k-icon :type="expanded ? 'angle-up' : 'angle-down'" />
            </span>
          </button>
          <span class="k-module-name">{{ module.moduleName }}</span>
          <button class="k-module-anchor" :aria-label="$t('modules.changeAnchor') + ': ' + module.slug" @click="$emit('change-slug')">
            #{{ module.slug }}
          </button>
        </div>
        <k-drawer-tabs class="k-module-tabs" :tab="activeTab" :tabs="tabs" @open="switchTab" />
        <button class="k-module-status" :data-status="module.status" :aria-label="isDraft ? $t('publish') : $t('modules.unpublish')" @click.stop="$emit('toggle-visibility')">
          <span>{{ isDraft ? $t("page.status.draft") : $t("page.status.listed") }}</span>
          <k-icon :type="isDraft ? 'hidden' : 'preview'" />
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

    <k-toolbar v-if="selected && !disabled" :buttons="toolbar" data-inline="true" class="k-module-toolbar"
      @mousedown.native.prevent />
  </div>
</template>

<script>
export default {
  // All state comes from props, all actions emit events
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
    };
  },
  computed: {
    isDraft() {
      return this.module.status === "draft";
    },
    // Locked by another, no update permission or translate: false
    disabled() {
      return (
        (this.module.lock && this.module.lock.isLocked) ||
        !(this.module.permissions && this.module.permissions.update)
      );
    },
    // Gate rendering until field values are loaded
    contentReady() {
      if (!this.module.hasFields) return true;
      return !!this.values && Object.keys(this.values).length > 0;
    },
    activeTab() {
      return this.currentTab || (this.module.tabs[0] && this.module.tabs[0].name);
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
          click: () => this.$go(this.module.link),
        },
        ...(this.module.previewUrl ? [{
          icon: "open",
          title: this.$t("preview"),
          click: () => window.open(this.module.previewUrl, "_blank"),
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
        },
        // Sort handle: drag target + keyboard ArrowUp/ArrowDown
        {
          icon: "sort",
          title: this.$t("sort"),
          class: "k-sort-handle",
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
              icon: this.isDraft ? "preview" : "hidden",
              label: this.isDraft ? this.$t("publish") : this.$t("modules.unpublish"),
              click: () => this.$emit("toggle-visibility"),
            },
            ...(this.module.previewUrl ? [{
              icon: "open",
              label: this.$t("preview"),
              link: this.module.previewUrl,
              target: "_blank",
            }] : []),
            "-",
            {
              icon: "template",
              label: this.$t("modules.changeType"),
              click: () => this.$emit("change-type"),
            },
            {
              icon: "hash",
              label: this.$t("modules.changeAnchor"),
              click: () => this.$emit("change-slug"),
            },
            {
              icon: "copy",
              label: this.$t("duplicate"),
              click: () => this.$emit("duplicate"),
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
  scroll-margin-block-start: var(--header-sticky-offset);

  &[data-status="draft"] {
    --module-color-back: color-mix(in srgb, light-dark(var(--color-white), var(--color-gray-850)), transparent 50%);
    box-shadow: none;
  }

  &[data-selected="true"] {
    outline: var(--outline);
  }

  &[data-disabled="true"] {
    /* TODO: more accessible disabled state? */
    pointer-events: none;
    opacity: 0.5;
  }

  &:is(.k-sortable-ghost, .k-sortable-fallback) .k-module-body {
    max-height: var(--drawer-header-height);
    overflow: clip;
  }

  &.k-sortable-ghost {
    outline: 2px solid var(--color-focus);
    box-shadow: rgba(17, 17, 17, 0.25) 0 5px 10px;
    cursor: grabbing;
  }
}

.k-module-body {
  &[data-collapsed="true"] {

    .k-module-content,
    .k-drawer-tabs {
      display: none;
    }
  }

  &:not([data-collapsed="true"]) {
    padding-block-end: var(--spacing-3);
  }
}

/* Header */

.k-module-header {
  display: grid;
  grid-template-columns: 1fr;
  height: var(--drawer-header-height);

  >* {
    grid-area: 1 / 1;
  }
}

/* Title */

.k-module-title {
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
  &:focus-visible {
    --show-arrow: true;
    color: var(--color-text);
  }
}

.k-module-icon {
  display: grid;

  >* {
    grid-area: 1 / 1;
  }

  > :last-child {
    visibility: hidden;
  }

  /* Turning the module icon into arrows on hover/focus */
  @container style(--show-arrow: true) {
    > :first-child {
      visibility: hidden;
    }

    > :last-child {
      visibility: visible;
    }
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

  &:hover,
  &:focus-visible {
    color: var(--color-text);
  }
}

/* Tabs */

.k-module-tabs {

  /* Needs higher specificity because Kirby uses this double class for drawer tabs */
  &.k-tabs {
    justify-content: center;
  }
}

/* Status */

.k-module-status {
  justify-self: end;

  display: flex;
  align-items: center;
  gap: var(--spacing-1);
  padding-inline: var(--spacing-3);

  font-size: var(--text-xs);
  color: var(--color-text-dimmed);
  border-radius: var(--rounded);

  /* Visually hide label for listed state, keep in accessibility tree */
  &[data-status="listed"] span {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
  }

  &:hover,
  &:focus-visible {
    color: var(--color-text);
  }
}

/* Content */

.k-module-content {
  background-color: var(--panel-color-back);
  border-radius: var(--rounded-sm);
  padding: var(--spacing-6) var(--spacing-6) var(--spacing-8);
  margin: 0 var(--spacing-3);
  container: column / inline-size;

  >.k-grid {
    gap: var(--spacing-6);
  }
}

.k-module-error {
  /* TODO: error state */
}

/* Toolbar */

.k-module-toolbar {
  --toolbar-size: 30px;
  display: none;
  position: absolute;
  inset-block-start: 0;
  inset-inline-end: var(--spacing-3);
  margin-block-start: calc(-1.75rem + 2px);
  box-shadow: var(--shadow-xl);
  border: 1px solid light-dark(var(--color-border), var(--color-gray-900));

  .k-module[data-selected="true"]>& {
    display: flex;
  }

  &>.k-button:not(:last-of-type) {
    border-inline-end: 1px solid var(--toolbar-border);
  }
}
</style>
