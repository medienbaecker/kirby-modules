<template>
  <k-form-dialog ref="dialog" size="large" v-bind="$props" class="k-module-create-dialog" @cancel="$emit('cancel')"
    @submit="$emit('submit', value)">
    <k-dialog-fields :fields="fields" :value="value" @input="$emit('input', $event)"
      @submit="$emit('submit', $event)" />
    <k-module-type-grid v-if="blueprints.length > 1" :types="blueprints" :selected="template" @select="pick" />
  </k-form-dialog>
</template>

<script>
export default {
  extends: "k-page-create-dialog",
};
</script>

<style scoped>
.k-module-type-field {
  margin-block-start: var(--spacing-4);
}
</style>
