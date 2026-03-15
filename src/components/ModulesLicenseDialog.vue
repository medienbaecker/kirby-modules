<template>
  <k-dialog class="k-modules-license-dialog" size="large" :cancel-button="cancelButton" :submit-button="submitButton"
    :visible="true" @cancel="$emit('cancel')">
    <k-bar style="margin-bottom: var(--spacing-2)">
      <h2 class="k-headline">
        Kirby Modules
      </h2>

      <k-button :text="$t('remove')" icon="trash" size="xs" variant="filled" dialog="modules/remove-license" />
    </k-bar>

    <div class="k-table">
      <table style="table-layout: auto">
        <tbody>
          <tr v-if="license.code">
            <th data-mobile="true">{{ $t("license.code") }}</th>
            <td data-mobile="true" class="k-text">
              <code>{{ license.code }}</code>
            </td>
          </tr>
          <tr v-if="license.version">
            <th data-mobile="true">{{ $t("version") }}</th>
            <td data-mobile="true">{{ license.version }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="k-modules-license-thanks">
      {{ $t("modules.license.active.info") }}
    </p>
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
  }
};
</script>

<style>
.k-modules-license-status {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
}

.k-modules-license-dialog .k-modules-license-status {
  --icon-color: var(--theme-color-700);
}

.k-modules-license-thanks {
  margin-block-start: var(--spacing-3);
  font-size: var(--text-xs);
  color: var(--color-text-dimmed);
}
</style>
