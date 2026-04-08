/* global Plugins, PluginHost, require */

Plugins.Scrolloff = {
	offset: __SCROLLOFF_OFFSET__,
	rafHandle: 0,

	getRows: function () {
		return Array.from(document.querySelectorAll("#headlines-frame > div[id*=RROW]"));
	},

	apply: function () {
		const container = document.getElementById("headlines-frame");
		const activeRow = document.querySelector("#headlines-frame > div[id*=RROW].active");

		if (!container || !activeRow) return;

		const rows = this.getRows();
		const activeIndex = rows.indexOf(activeRow);

		if (activeIndex === -1) return;

		const topIndex = Math.max(0, activeIndex - this.offset);
		const bottomIndex = Math.min(rows.length - 1, activeIndex + this.offset);

		const topRow = rows[topIndex];
		const bottomRow = rows[bottomIndex];

		const wantedTop = topRow.offsetTop;
		const wantedBottom = bottomRow.offsetTop + bottomRow.offsetHeight;

		const viewTop = container.scrollTop;
		const viewBottom = viewTop + container.clientHeight;

		if (wantedTop < viewTop) {
			container.scrollTop = wantedTop;
		} else if (wantedBottom > viewBottom) {
			container.scrollTop = wantedBottom - container.clientHeight;
		}
	},

	schedule: function () {
		window.cancelAnimationFrame(this.rafHandle);

		this.rafHandle = window.requestAnimationFrame(() => {
			this.apply();
		});
	}
};

require(["dojo/ready"], (ready) => {
	ready(() => {
		const self = Plugins.Scrolloff;

		PluginHost.register(PluginHost.HOOK_ARTICLE_SET_ACTIVE, () => {
			self.schedule();
			return true;
		});

		PluginHost.register(PluginHost.HOOK_HEADLINES_RENDERED, () => {
			self.schedule();
			return true;
		});

		window.addEventListener("resize", () => {
			self.schedule();
		});
	});
});
