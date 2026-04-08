<?php
class Scrolloff extends Plugin {
	private const DEFAULT_OFFSET = 2;
	private const MAX_OFFSET = 50;

	function about() {
		return [null,
			"Keeps the active headline away from viewport edges",
			"OpenCode"];
	}

	function init($host) {
		$host->add_hook($host::HOOK_PREFS_TAB_SECTION, $this);
	}

	function api_version() {
		return 2;
	}

	private function get_offset() : int {
		$offset = (int) PluginHost::getInstance()->profile_get($this, "offset", self::DEFAULT_OFFSET);

		if ($offset < 0) $offset = 0;
		if ($offset > self::MAX_OFFSET) $offset = self::MAX_OFFSET;

		return $offset;
	}

	function get_js() {
		$js = file_get_contents(__DIR__ . "/init.js");

		return str_replace("__SCROLLOFF_OFFSET__", (string) $this->get_offset(), $js);
	}

	function hook_prefs_tab_section($id) {
		if ($id !== "prefPrefsPrefsInside") return;

		$offset = $this->get_offset();
		?>
		<hr/>

		<h2><?= __("Headline navigation") ?></h2>

		<form dojoType='dijit.form.Form' id='scrolloff-config-form'>
			<?= \Controls\hidden_tag("op", "PluginHandler") ?>
			<?= \Controls\hidden_tag("plugin", "scrolloff") ?>
			<?= \Controls\hidden_tag("method", "save") ?>
			<?= \Controls\hidden_tag("csrf_token", $_SESSION["csrf_token"]) ?>

			<script type="dojo/method" event="onSubmit" args="evt">
				evt.preventDefault();

				if (this.validate()) {
					Notify.progress("Saving scrolloff setting...", true);

					xhr.post("backend.php", this.getValues(), (reply) => {
						Notify.info(reply);
					});
				}
			</script>

			<fieldset class='prefs'>
				<label for='scrolloff_offset'><?= __("Headline scrolloff:") ?></label>

				<input
					dojoType='dijit.form.NumberSpinner'
					id='scrolloff_offset'
					name='offset'
					value='<?= $offset ?>'
					smallDelta='1'
					constraints='{min:0,max:50,places:0}'
					required='1'
				>

				<div class='help-text text-muted'>
					<?= __("Keep this many headlines visible above and below the active article while navigating.") ?>
				</div>

				<div class='help-text text-muted'>
					<?= __("Works with any shortcut that changes the active article. Reload the main tt-rss tab after saving.") ?>
				</div>
			</fieldset>

			<button dojoType='dijit.form.Button' type='submit' class='alt-primary'>
				<?= \Controls\icon("save") ?>
				<?= __("Save scrolloff") ?>
			</button>
		</form>
		<?php
	}

	function save() : void {
		$offset = (int) clean($_POST["offset"] ?? self::DEFAULT_OFFSET);

		if ($offset < 0) $offset = 0;
		if ($offset > self::MAX_OFFSET) $offset = self::MAX_OFFSET;

		PluginHost::getInstance()->profile_set($this, "offset", $offset);

		print T_sprintf("Scrolloff saved (%d). Reload the main tt-rss tab to apply.", $offset);
	}
}
