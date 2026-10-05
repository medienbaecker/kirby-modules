<template>
  <k-form-dialog ref="dialog" size="large" v-bind="$props" class="k-module-create-dialog" @cancel="$emit('cancel')"
    @submit="$emit('submit', value)">
    <k-module-type-grid v-if="blueprints.length > 1" :types="blueprints" :groups="groups" :selected="template"
      @select="pick" />
    <k-dialog-fields :fields="fields" :value="value" @input="$emit('input', $event)"
      @submit="$emit('submit', $event)" />
  </k-form-dialog>
</template>

<script>
export default {
  extends: "k-page-create-dialog",
  props: {
    groups: { type: [Object, Array], default: null },
  },
};
</script>

<style scoped>
.k-module-type-field {
  &:has(+ .k-dialog-fields .k-field) {
    margin-block-end: var(--spacing-8);
    padding-block-end: var(--spacing-8);
    border-block-end: 1px dashed light-dark(var(--color-gray-400), var(--color-gray-800));
  }
}
</style>
