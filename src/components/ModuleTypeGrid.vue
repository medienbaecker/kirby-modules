<template>
  <div class="k-module-type-field">
    <template v-if="hasPreviews || hasGroups">
      <header class="k-field-header">
        <label class="k-label k-field-label"><span class="k-label-text">{{ $t("modules.create.type") }}</span></label>
      </header>
      <component :is="group.label ? 'details' : 'div'" v-for="group in resolvedGroups" :key="group.key"
        class="k-module-type-group" :open="group.label ? group.open : null">
        <summary v-if="group.label">{{ group.label }}</summary>
        <k-navigate class="k-module-types">
          <button v-for="type in group.types" :key="type.name" type="button" class="k-module-type"
            :aria-current="type.name === selected" :aria-label="type.title" :disabled="type.disabled"
            :data-autofocus="type.name === selected" @click="$emit('select', type.name)">
            <k-item-image class="k-module-type-image" :image="image(type)" layout="cards" />
            <span class="k-module-type-label">{{ type.title }}</span>
          </button>
        </k-navigate>
      </component>
    </template>
    <k-select-field v-else :label="$t('modules.create.type')" :options="typeOptions" :value="selected" :empty="false"
      :required="true" @input="$emit('select', $event)" />
  </div>
</template>

<script>
export default {
  props: {
    types: { type: Array, default: () => [] },
    groups: { type: [Object, Array], default: null },
    selected: String,
  },
  data() {
    return { openedWith: this.selected };
  },
  computed: {
    hasPreviews() {
      return this.types.some((type) => type.preview);
    },
    hasGroups() {
      return this.resolvedGroups.some((group) => group.label);
    },
    // Mirrors k-block-selector: with groups declared it renders those and only
    // those, so a type in no group is not offered. Without them, one unlabelled
    // group stands in for the whole picker.
    resolvedGroups() {
      const byName = new Map(this.types.map((type) => [type.name, type]));
      const groups = [];

      for (const key in this.groups ?? {}) {
        const group = this.groups[key];
        const groupTypes = (group.templates ?? [])
          .map((name) => byName.get(name))
          .filter(Boolean);

        if (groupTypes.length === 0) continue;

        const holdsOpeningSelection = groupTypes.some((type) => type.name === this.openedWith);

        groups.push({
          key,
          label: group.label,
          open: group.open || holdsOpeningSelection,
          types: groupTypes
        });
      }

      if (groups.length === 0) {
        return [{ key: "_", label: null, types: this.types }];
      }

      return groups;
    },
    typeOptions() {
      return this.types.map((type) => ({
        value: type.name,
        text: type.title,
        disabled: type.disabled,
      }));
    },
  },
  methods: {
    image(type) {
      if (type.preview) {
        return { src: type.preview, cover: true, ratio: "16/9", back: "pattern" };
      }
      return {
        icon: type.icon || "box",
        ratio: "16/9",
        back: "pattern",
        color: "var(--color-white)",
      };
    },
  },
};
</script>

<style scoped>
.k-module-type-field:has(summary) > .k-field-header {
  margin-bottom: var(--spacing-1);
}

.k-module-type-group {
  & + & {
    margin-top: var(--spacing-4);
  }

  &:not([open]) + & {
    margin-top: 0;
  }

  & > summary {
    font-size: var(--text-xs);
    color: var(--color-text-dimmed);
    cursor: pointer;
    max-inline-size: fit-content;
    padding-block: var(--spacing-2);
    border-radius: var(--rounded);

    &:hover {
      color: var(--color-text);
    }

    &:focus-visible {
      outline: var(--outline);
      outline-offset: 2px;
      color: var(--color-text);
    }

    & + .k-module-types {
      margin-top: var(--spacing-1);
    }
  }
}

.k-module-types {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
  gap: var(--spacing-3);
}

.k-module-type {
  display: flex;
  flex-direction: column;
  text-align: start;
  background: light-dark(var(--color-white), var(--color-gray-850));
  box-shadow: var(--shadow);
  border-radius: var(--rounded);
  overflow: hidden;
  cursor: pointer;

  &:focus-visible {
    outline: var(--outline);
    outline-offset: 2px;
  }

  &[aria-current="true"] {
    box-shadow: 0 0 0 2px var(--color-focus);
  }

  &:disabled {
    opacity: var(--opacity-disabled);
    cursor: not-allowed;
  }
}

.k-module-type-label {
  padding: var(--spacing-2);
  font-size: var(--text-sm);
  line-height: 1.25;
}
</style>
