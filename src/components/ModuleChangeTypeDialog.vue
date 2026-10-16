<template>
  <k-form-dialog ref="dialog" size="large" v-bind="$props" class="k-module-change-type-dialog" @cancel="$emit('cancel')"
    @submit="$emit('submit', value)">
    <k-module-type-grid v-if="showsCards" :types="blueprints" :groups="groups" :selected="value.template"
      @select="select" />
    <k-dialog-fields v-else :fields="{ template: selectField }" :value="value" @input="select($event.template)"
      @submit="$emit('submit', value)" />
  </k-form-dialog>
</template>

<script>
import { showsTypeCards, typeSelectField } from "../helpers/typePicker.js";

export default {
  extends: "k-form-dialog",
  props: {
    blueprints: { type: Array, default: () => [] },
    groups: { type: [Object, Array], default: null },
  },
  computed: {
    showsCards() {
      return showsTypeCards(this.blueprints, this.groups);
    },
    selectField() {
      return typeSelectField(this.blueprints);
    },
  },
  methods: {
    select(name) {
      this.$emit("input", { ...this.value, template: name });
    },
  },
};
</script>
