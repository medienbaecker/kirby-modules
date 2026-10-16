export function showsTypeCards(types, groups) {
  if (types.some((type) => type.preview)) return true;

  const names = new Set(types.map((type) => type.name));

  return Object.values(groups ?? {}).some(
    (group) => group.label && (group.templates ?? []).some((name) => names.has(name)),
  );
}

export function typeSelectField(types) {
  return {
    label: window.panel.t("modules.create.type"),
    type: "select",
    empty: false,
    required: true,
    options: types.map((type) => ({
      value: type.name,
      text: type.title,
      disabled: type.disabled,
    })),
  };
}
