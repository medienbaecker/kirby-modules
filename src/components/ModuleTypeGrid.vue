<template>
  <div class="k-module-type-field">
    <template v-if="hasPreviews">
      <header class="k-field-header">
        <label class="k-label k-field-label"><span class="k-label-text">{{ $t("modules.create.type") }}</span></label>
      </header>
      <template v-if="hasGroups">
        <template v-for="group in resolvedGroups">
          <details v-if="group.label" :key="group.key" class="k-section" :open="group.open">
            <summary>{{ group.label }}</summary>
            <k-navigate class="k-module-types">
              <button v-for="type in group.types" :key="type.name" type="button" class="k-module-type"
                :aria-current="type.name === selected" :aria-label="type.title" :disabled="type.disabled"
                :data-autofocus="type.name === selected" @click="$emit('select', type.name)">
                <k-item-image class="k-module-type-image" :image="image(type)" layout="cards" />
                <span class="k-module-type-label">{{ type.title }}</span>
              </button>
            </k-navigate>
          </details>
          <k-navigate v-else :key="group.key" class="k-module-types k-section">
            <button v-for="type in group.types" :key="type.name" type="button" class="k-module-type"
              :aria-current="type.name === selected" :aria-label="type.title" :disabled="type.disabled"
              :data-autofocus="type.name === selected" @click="$emit('select', type.name)">
              <k-item-image class="k-module-type-image" :image="image(type)" layout="cards" />
              <span class="k-module-type-label">{{ type.title }}</span>
            </button>
          </k-navigate>
        </template>
      </template>
      <k-navigate v-else class="k-module-types">
        <button v-for="type in types" :key="type.name" type="button" class="k-module-type"
          :aria-current="type.name === selected" :aria-label="type.title" :disabled="type.disabled"
          :data-autofocus="type.name === selected" @click="$emit('select', type.name)">
          <k-item-image class="k-module-type-image" :image="image(type)" layout="cards" />
          <span class="k-module-type-label">{{ type.title }}</span>
        </button>
      </k-navigate>
    </template>
    <k-select-field v-else :label="$t('modules.create.type')" :options="typeOptions" :value="selected" :empty="false"
      :required="true" @input="$emit('select', $event)" />
  </div>
</template>

<script>
export default {
  props: {
    types: { type: Array, default: () => [] },
    // Same shape as core's fieldsetGroups: { [key]: { label, open, sets } },
    // sets holding member type names. Declared on the modules section's
    // blueprint, not on each type - grouping is opt-in and purely additive,
    // null/empty renders exactly like it always has.
    groups: { type: [Object, Array], default: null },
    selected: String,
  },
  computed: {
    hasPreviews() {
      return this.types.some((type) => type.preview);
    },
    hasGroups() {
      // Not just resolvedGroups.length: an all-leftover result (no group
      // matched anything) must still fall through to the plain flat grid.
      return this.resolvedGroups.some((group) => group.label);
    },
    // Mirrors k-block-selector's own groups(): filters each group's `sets`
    // down to types that actually exist, in the blueprint's declared order.
    // Types no group claims are collected under a heading-less group that
    // always leads (e.g. while only some types have been sorted into groups).
    resolvedGroups() {
      const byName = new Map(this.types.map((type) => [type.name, type]));
      const result = [];
      const claimed = new Set();

      for (const key in this.groups ?? {}) {
        const group = this.groups[key];
        const groupTypes = (group.sets ?? [])
          .map((name) => byName.get(name))
          .filter(Boolean);
        if (groupTypes.length === 0) continue;

        groupTypes.forEach((type) => claimed.add(type.name));
        result.push({ key, label: group.label, open: group.open !== false, types: groupTypes });
      }

      const leftover = this.types.filter((type) => !claimed.has(type.name));
      if (leftover.length > 0) {
        result.unshift({ key: "_", label: null, open: true, types: leftover });
      }

      return result;
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
/* 1rem hardcoded (not a --spacing-* token) to match k-block-selector's own
   .k-headline margin-bottom, which does the same summary-to-content gap */
summary {
  margin-bottom: 1rem;
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
