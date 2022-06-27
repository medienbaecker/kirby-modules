panel.plugin('medienbaecker/modules', {
  components: {
    'k-modules-section': {
      extends: 'k-pages-section',
      created: function () {
        if (this.parent == 'site') return;
        this.$api.post(this.parent + '/modules').then((data) => {
          if (data.created) {
            this.reload();
          }
        });
      },
      computed: {
        type() {
          return 'modules';
        }
      },
      methods: {
        onAdd() {
          if (this.canAdd) {
            this.$dialog('pages/create', {
              query: {
                parent: this.options.link || this.parent,
                view: this.parent,
                section: this.name,
                modules: this.options.layout
              }
            });
          }
        }
      }
    }
  },
  fields: {
    modules_redirect: {
      props: {
        redirect: String
      },
      render: function () {
        window.location.href = this.redirect;
      }
    }
  }
});
