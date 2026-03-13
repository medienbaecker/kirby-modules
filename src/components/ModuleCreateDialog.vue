<template>
  <k-form-dialog
    ref="dialog"
    v-bind="$props"
    class="k-module-create-dialog"
    @cancel="$emit('cancel')"
    @submit="$emit('submit', value)"
  >
    <k-select-field
      v-if="templates.length > 1"
      :empty="false"
      :label="$t('modules.create.type')"
      :options="templates"
      :required="true"
      :value="template"
      @input="pick($event)"
    />
    <k-dialog-fields
      :fields="fields"
      :value="value"
      @input="$emit('input', $event)"
      @submit="$emit('submit', $event)"
    />
  </k-form-dialog>
</template>

<script>
export default {
  extends: "k-page-create-dialog",
};
</script>

<style>
.k-dialog-body > .k-select-field {
  margin-bottom: var(--spacing-6);
}
</style>
