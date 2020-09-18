panel.plugin('medienbaecker/modules', {
	components: {
		'k-modules-section': {
			extends: 'k-pages-section',
			updated: function () {
				this.$nextTick(function () {
					this.$el.classList.add('k-modules-section');
				})
			}
		},
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