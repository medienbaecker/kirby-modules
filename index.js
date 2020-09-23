panel.plugin('medienbaecker/modules', {
	components: {
		'k-modules-section': {
			extends: 'k-pages-section',
			created: function () {
				this.$api.post(this.parent + '/modules')
					.then((data) => {
						if (data.created) {
							this.reload();
						}
					});
			},
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