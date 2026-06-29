<template>
  <k-form-dialog ref="dialog" size="large" v-bind="$props" class="k-module-create-dialog" @cancel="$emit('cancel')"
    @submit="$emit('submit', value)">
    <k-module-type-grid v-if="blueprints.length > 1" :types="blueprints" :selected="template" @select="pick" />
    <k-dialog-fields :fields="fields" :value="value" @input="$emit('input', $event)"
      @submit="$emit('submit', $event)" />
  </k-form-dialog>
</template>

<script>
export default {
  extends: "k-page-create-dialog",
};
</script>

<style scoped>
.k-module-type-field {
  /* match native .k-page-template-switch: dashed divider under the type picker */
  margin-block-end: var(--spacing-6);
  padding-block-end: var(--spacing-6);
  border-block-end: 1px dashed var(--color-gray-300);
}
</style>
