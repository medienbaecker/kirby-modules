<template>
  <div class="k-module-type-field">
    <template v-if="hasPreviews">
      <header class="k-field-header">
        <label class="k-label k-field-label"><span class="k-label-text">{{ $t("modules.create.type") }}</span></label>
      </header>
      <k-navigate class="k-module-types">
        <button v-for="type in types" :key="type.name" type="button" class="k-module-type"
          :aria-current="type.name === selected" :aria-label="type.title" :disabled="type.disabled"
          @click="$emit('select', type.name)">
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
    selected: String,
  },
  computed: {
    hasPreviews() {
      return this.types.some((type) => type.preview);
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
