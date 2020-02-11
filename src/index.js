panel.plugin('medienbaecker/modules', {
	components: {
	  'k-modules-section': {
		extends: 'k-pages-section'
	  }
	},
	fields: {
		modules_redirect: {
			props: {
				redirect: String
			},
			render: function() {
				window.location.href = this.redirect;
			}
		}
	}
});