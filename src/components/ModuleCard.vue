<template>
  <div class="k-module" :data-module-id="module.id" :data-status="module.status"
    :data-selected="selected" :data-disabled="disabled"
    :tabindex="disabled ? null : 0" @focusin.stop="$emit('select')">
    <div class="k-module-body" :data-collapsed="!expanded">
      <header class="k-module-header">
        <button class="k-module-title" @click="$emit('toggle')">
          <k-icon v-if="loading" type="loader" />
          <span v-else class="k-module-icon">
            <k-icon :type="module.icon" />
            <k-icon :type="expanded ? 'angle-up' : 'angle-down'" />
          </span>
          <span>{{ module.moduleName }}</span>
        </button>
        <button class="k-module-status" :data-status="module.status" @click.stop="$emit('toggle-visibility')">
          <span>{{ isDraft ? $t("page.status.draft") : $t("page.status.listed") }}</span>
          <k-icon :type="isDraft ? 'hidden' : 'preview'" />
        </button>
        <k-drawer-tabs :tab="activeTab" :tabs="tabs" @open="switchTab" />
      </header>
      <div v-if="contentReady" class="k-module-content">
        <k-sections v-for="tab in module.tabs" v-show="expanded && activeTab === tab.name"
          :key="tab.name" :parent="pageUrl" :tab="tab" :content="values"
          @input="$emit('input', $event)" />
      </div>
      <k-empty v-if="hasError" icon="alert" layout="cardlets" class="k-module-error">
        {{ $t("error") }}
      </k-empty>
    </div>

    <k-toolbar v-if="selected && !disabled" :buttons="toolbar"
      data-inline="true" class="k-module-toolbar" @mousedown.native.prevent />
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
    };
  },
  computed: {
    isDraft() {
      return this.module.status === "draft";
    },
    disabled() {
      return (
        (this.module.lock && this.module.lock.isLocked) ||
        !(this.module.permissions && this.module.permissions.update)
      );
    },
    contentReady() {
      if (!this.module.hasFields) return true;
      return !!this.values && Object.keys(this.values).length > 0;
    },
    activeTab() {
      return this.currentTab || (this.module.tabs[0] && this.module.tabs[0].name);
    },
    tabs() {
      return this.module.tabs.map(({ link, ...tab }) => tab);
    },
    toolbar() {
      return [
        {
          icon: "edit",
          title: this.$t("edit"),
          click: () => this.$go(this.module.link),
        },
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
              icon: "template",
              label: this.$t("field.blocks.changeType"),
              click: () => this.$emit("change-type"),
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
            {
              icon: this.isDraft ? "preview" : "hidden",
              label: this.isDraft ? this.$t("publish") : this.$t("modules.unpublish"),
              click: () => this.$emit("toggle-visibility"),
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

  &[data-status="draft"] {
    --module-color-back: color-mix(in srgb, light-dark(var(--color-white), var(--color-gray-850)), transparent 50%);
    box-shadow: none;
  }

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
  display: grid;
  align-items: center;
  padding-inline-end: var(--spacing-3);

  @media (min-width: 60rem) {
    grid-template-columns: 1fr;
  }

  @media (max-width: 60rem) {
    grid-template-columns: 1fr auto;
  }
}

@media (min-width: 60rem) {
  .k-module-header>* {
    grid-row: 1;
    grid-column: 1;
  }

  .k-module-header .k-drawer-tabs {
    justify-content: center;
  }

  .k-module-header .k-module-status {
    justify-self: end;
    z-index: 1;
  }
}

@media (max-width: 60rem) {
  .k-module-header .k-drawer-tabs {
    grid-row: 2;
    grid-column: 1 / -1;
    justify-content: center;
  }

  .k-module-header .k-module-status {
    grid-row: 1;
    grid-column: 2;
  }
}

.k-module-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3);
  border-radius: var(--rounded);
  max-width: fit-content;

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

.k-module-title:hover .k-icon {
  color: light-dark(var(--color-gray-600), var(--color-gray-400));
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

.k-module-status {
  display: flex;
  align-items: center;
  gap: var(--spacing-1);
  font-size: var(--text-xs);
  color: var(--color-gray-500);
  padding: var(--spacing-1);
  border-radius: var(--rounded);

  span {
    color: var(--color-text-dimmed);
    visibility: hidden;
  }

  &:hover,
  &:focus-visible {
    color: light-dark(var(--color-gray-600), var(--color-gray-400));

    span {
      visibility: inherit;
    }
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
</style>
