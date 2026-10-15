<template>
  <k-dialog class="k-modules-license-dialog" size="large" :cancel-button="cancelButton" :submit-button="submitButton"
    :visible="true" @cancel="$emit('cancel')">
    <k-stack>
      <k-bar>
        <h2 class="k-headline">
          Kirby Modules
        </h2>

        <k-button :text="$t('remove')" icon="trash" size="xs" variant="filled" dialog="modules/remove-license" />
      </k-bar>

      <k-definitions>
        <k-definition v-if="license.code" :term="$t('license.code')">
          <k-code-token type="pink">{{ license.code }}</k-code-token>
        </k-definition>
        <k-definition v-if="license.version" :term="$t('version')" :description="license.version" />
      </k-definitions>

      <p class="k-modules-license-thanks">
        {{ $t("modules.license.active.info") }}
      </p>
    </k-stack>
  </k-dialog>
</template>

<script>
export default {
  props: {
    license: {
      type: Object,
      default: () => ({})
    },
    cancelButton: {
      default: false
    },
    submitButton: {
      default: false
    }
  },
  emits: ["cancel"]
};
</script>

<style>
.k-modules-license-thanks {
  font-size: var(--text-xs);
  color: var(--color-text-dimmed);
}
</style>
