<template>
  <k-dialog ref="dialog" v-bind="$props" class="k-page-move-dialog"
    :submit-button="{ icon: 'parent', text: $t('move') }" size="medium"
    @cancel="$emit('cancel')" @submit="$emit('submit', value)">
    <k-headline>{{ $t("modules.move.headline") }}</k-headline>
    <div class="k-page-move-parent" tabindex="0" data-autofocus>
      <k-page-tree :current="value.parent" :move="value.move" identifier="id" @select="select" />
    </div>
  </k-dialog>
</template>

<script>
export default {
  mixins: ["dialog"],
  props: {
    value: { type: Object, default: () => ({}) },
  },
  emits: ["cancel", "input", "submit"],
  methods: {
    select(page) {
      this.$emit("input", { ...this.value, parent: page.value });
    },
  },
};
</script>
