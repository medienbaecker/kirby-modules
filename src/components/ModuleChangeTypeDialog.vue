<template>
  <k-form-dialog ref="dialog" size="large" v-bind="$props" class="k-module-change-type-dialog" @cancel="$emit('cancel')"
    @submit="$emit('submit', value)">
    <k-module-type-grid :types="blueprints" :selected="value.template" @select="select" />
  </k-form-dialog>
</template>

<script>
export default {
  extends: "k-form-dialog",
  props: {
    blueprints: { type: Array, default: () => [] },
  },
  methods: {
    select(name) {
      this.$emit("input", { ...this.value, template: name });
    },
  },
};
</script>
