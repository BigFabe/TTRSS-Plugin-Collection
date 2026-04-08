/* global Plugins, App, Notify, xhr, __ */

Plugins.Share_To_Karakeep = {
	saveArticle: function(id) {
		const icon = document.querySelector('.karakeep-icon-' + id);

		if (icon?.classList.contains('is-saving')) {
			return;
		}

		icon?.classList.add('is-saving');
		Notify.progress(__('Saving to Karakeep...'), true);

		xhr.json('backend.php', App.getPhArgs('share_to_karakeep', 'saveArticle', {id: id}), (reply) => {
			Notify.close();
			icon?.classList.remove('is-saving');

			if (reply && reply.status === 'ok') {
				icon?.classList.add('is-saved');
				Notify.info(reply.message || __('Saved to Karakeep.'));
			} else {
				Notify.error(reply?.message || __('Could not save article to Karakeep.'));
			}
		});
	}
};
