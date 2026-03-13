import ModulesSection from "./components/ModulesSection.vue";
import ModuleCreateDialog from "./components/ModuleCreateDialog.vue";

panel.plugin("medienbaecker/modules", {
  components: {
    "k-modules-section": ModulesSection,
    "k-module-create-dialog": ModuleCreateDialog,
  },
  icons: {
    "add-module-above":
      '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 9V5H13V9H17V11H13V15H11V11H7V9H11ZM12 20C6.47715 20 2 15.5228 2 10C2 4.47715 6.47715 0 12 0C17.5228 0 22 4.47715 22 10C22 15.5228 17.5228 20 12 20ZM12 18C16.4183 18 20 14.4183 20 10C20 5.58172 16.4183 2 12 2C7.58172 2 4 5.58172 4 10C4 14.4183 7.58172 18 12 18Z"/><path d="M21 23C21 22.4477 20.5523 22 20 22H4C3.44772 22 3 22.4477 3 23C3 23.5523 3.44772 24 4 24H5.00001H19H20C20.5523 24 21 23.5523 21 23Z"/></svg>',
    "add-module-below":
      '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 15V19H13V15H17V13H13V9H11V13H7V15H11ZM12 4C6.47715 4 2 8.4772 2 14C2 19.5229 6.47715 24 12 24C17.5228 24 22 19.5229 22 14C22 8.4772 17.5228 4 12 4ZM12 6C16.4183 6 20 9.5817 20 14C20 18.4183 16.4183 22 12 22C7.58172 22 4 18.4183 4 14C4 9.5817 7.58172 6 12 6Z"/><path d="M21 1C21 1.5523 20.5523 2 20 2H4C3.44772 2 3 1.5523 3 1C3 0.4477 3.44772 0 4 0H5.00001H19H20C20.5523 0 21 0.4477 21 1Z"/></svg>',
  },
});
