<?php

class Share_To_Karakeep extends Plugin {
	/** @var PluginHost */
	private $host;

	function about() {
		return [null,
			"Save articles to Karakeep",
			"OpenCode"];
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook(PluginHost::HOOK_ARTICLE_BUTTON, $this);
		$host->add_hook(PluginHost::HOOK_PREFS_TAB, $this);
	}

	function flags() {
		return ["needs_curl" => true];
	}

	function get_js() {
		return file_get_contents(__DIR__ . "/init.js");
	}

	function get_css() {
		return file_get_contents(__DIR__ . "/init.css");
	}

	function hook_article_button($line) {
		$int_id = (int)($line["int_id"] ?? 0);

		if (!$int_id) {
			return "";
		}

		$title = htmlspecialchars(__('Save to Karakeep'));

		return "<i class='material-icons icon-karakeep karakeep-icon-$int_id'
			style='cursor : pointer' onclick=\"Plugins.Share_To_Karakeep.saveArticle($int_id)\"
			title=\"$title\">bookmark</i>";
	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		$server_url = (string)$this->host->get($this, "server_url", "");
		$api_key = (string)$this->host->get($this, "api_key", "");
		$default_tags = (string)$this->host->get($this, "default_tags", "");
		$favourited = sql_bool_to_bool($this->host->get($this, "favourited", ""));
		$archived = sql_bool_to_bool($this->host->get($this, "archived", ""));
		?>
		<div dojoType="dijit.layout.AccordionPane"
			title="<i class='material-icons'>bookmark</i> <?= __('Karakeep') ?>">
			<form dojoType="dijit.form.Form">

				<?= \Controls\pluginhandler_tags($this, "save") ?>

				<script type="dojo/method" event="onSubmit" args="evt">
					evt.preventDefault();
					if (this.validate()) {
						Notify.progress('Saving data...', true);
						xhr.post("backend.php", this.getValues(), (reply) => {
							Notify.info(reply);
						});
					}
				</script>

				<header><?= __('Store current article links in Karakeep with one click.') ?></header>

				<fieldset>
					<label><?= __('Karakeep URL:') ?></label>
					<input dojoType="dijit.form.ValidationTextBox"
						required="1"
						style="width: 32em"
						name="server_url"
						placeHolder="https://karakeep.example.com"
						value="<?= htmlspecialchars($server_url) ?>">
				</fieldset>

				<fieldset>
					<label><?= __('API key:') ?></label>
					<input dojoType="dijit.form.ValidationTextBox"
						required="1"
						type="password"
						style="width: 32em"
						name="api_key"
						value="<?= htmlspecialchars($api_key) ?>">
				</fieldset>

				<fieldset>
					<label><?= __('Default tags (comma-separated):') ?></label>
					<input dojoType="dijit.form.ValidationTextBox"
						style="width: 32em"
						name="default_tags"
						placeHolder="rss, read-later"
						value="<?= htmlspecialchars($default_tags) ?>">
				</fieldset>

				<fieldset>
					<label class='checkbox'>
						<?= \Controls\checkbox_tag("favourited", $favourited) ?>
						<?= __('Mark saved bookmarks as favorites') ?>
					</label>
				</fieldset>

				<fieldset>
					<label class='checkbox'>
						<?= \Controls\checkbox_tag("archived", $archived) ?>
						<?= __('Archive saved bookmarks immediately') ?>
					</label>
				</fieldset>

				<hr/>

				<?= \Controls\submit_tag(__('Save')) ?>
			</form>
		</div>
		<?php
	}

	function save() : void {
		$server_url = trim((string)($_POST["server_url"] ?? ""));
		$api_key = trim((string)($_POST["api_key"] ?? ""));
		$default_tags = $this->normalize_tags((string)($_POST["default_tags"] ?? ""));
		$favourited = checkbox_to_sql_bool($_POST["favourited"] ?? "");
		$archived = checkbox_to_sql_bool($_POST["archived"] ?? "");

		if (!$server_url || !$api_key) {
			print __('Karakeep URL and API key are required.');
			return;
		}

		$this->host->set_array($this, [
			"server_url" => $server_url,
			"api_key" => $api_key,
			"default_tags" => $default_tags,
			"favourited" => $favourited,
			"archived" => $archived,
		]);

		print __('Karakeep configuration saved.');
	}

	function saveArticle() : void {
		$int_id = (int)clean($_REQUEST["id"] ?? 0);

		if (!$int_id) {
			$this->print_json(["status" => "error", "message" => __('Article not found.')]);
			return;
		}

		$config = $this->get_config();
		if (!$config["server_url"] || !$config["api_key"]) {
			$this->print_json([
				"status" => "error",
				"message" => __('Please configure Karakeep in Preferences first.')
			]);
			return;
		}

		$article = $this->get_article($int_id);
		if (!$article) {
			$this->print_json(["status" => "error", "message" => __('Article not found.')]);
			return;
		}

		if (empty($article["link"])) {
			$this->print_json([
				"status" => "error",
				"message" => __('This article has no link to save.')
			]);
			return;
		}

		$payload = [
			"type" => "link",
			"url" => (string)$article["link"],
			"title" => $this->normalize_text((string)$article["title"]),
			"favourited" => $config["favourited"],
			"archived" => $config["archived"],
			"crawlPriority" => "normal",
		];

		$result = $this->request_karakeep($config, "POST", "/bookmarks", $payload);
		if (!$result["ok"]) {
			$this->print_json([
				"status" => "error",
				"message" => $result["message"],
			]);
			return;
		}

		$bookmark_id = (string)($result["data"]["id"] ?? "");
		$tags = $this->parse_tags((string)$config["default_tags"]);

		if ($bookmark_id && count($tags) > 0) {
			$tag_payload = [
				"tags" => array_map(function ($tag) {
					return ["tagName" => $tag];
				}, $tags)
			];

			$tag_result = $this->request_karakeep($config, "POST", "/bookmarks/{$bookmark_id}/tags", $tag_payload);

			if (!$tag_result["ok"]) {
				$this->print_json([
					"status" => "error",
					"message" => __('Bookmark saved, but tags could not be attached: ') . $tag_result["message"],
				]);
				return;
			}
		}

		$this->print_json([
			"status" => "ok",
			"message" => __('Saved to Karakeep.'),
			"bookmark_id" => $bookmark_id,
		]);
	}

	private function get_config() : array {
		return [
			"server_url" => $this->normalize_server_url((string)$this->host->get($this, "server_url", "")),
			"api_key" => trim((string)$this->host->get($this, "api_key", "")),
			"default_tags" => (string)$this->host->get($this, "default_tags", ""),
			"favourited" => sql_bool_to_bool($this->host->get($this, "favourited", "")),
			"archived" => sql_bool_to_bool($this->host->get($this, "archived", "")),
		];
	}

	private function get_article(int $int_id) {
		$sth = $this->pdo->prepare("SELECT
				ttrss_entries.id AS id,
				ttrss_user_entries.int_id,
				ttrss_user_entries.feed_id,
				ttrss_entries.title,
				ttrss_entries.link,
				ttrss_entries.author,
				ttrss_entries.updated,
				ttrss_user_entries.note,
				ttrss_feeds.title AS feed_title
			FROM ttrss_entries
				JOIN ttrss_user_entries ON (ttrss_user_entries.ref_id = ttrss_entries.id)
				LEFT JOIN ttrss_feeds ON (ttrss_feeds.id = ttrss_user_entries.feed_id)
			WHERE ttrss_user_entries.int_id = ? AND ttrss_user_entries.owner_uid = ?");
		$sth->execute([$int_id, $_SESSION["uid"]]);

		return $sth->fetch();
	}

	private function request_karakeep(array $config, string $method, string $path, array $payload) : array {
		$url = rtrim((string)$config["server_url"], "/") . $path;
		$body = json_encode($payload);

		if ($body === false) {
			return [
				"ok" => false,
				"message" => __('Could not encode request payload.')
			];
		}

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => [
				"Accept: application/json",
				"Authorization: Bearer " . $config["api_key"],
				"Content-Type: application/json",
			],
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 20,
		]);

		$response = curl_exec($ch);
		if ($response === false) {
			$message = curl_error($ch);
			curl_close($ch);

			return [
				"ok" => false,
				"message" => $message ?: __('Request to Karakeep failed.')
			];
		}

		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$data = json_decode($response, true);
		if ($status >= 200 && $status < 300) {
			return [
				"ok" => true,
				"data" => is_array($data) ? $data : [],
			];
		}

		$message = is_array($data) ? (string)($data["message"] ?? $data["code"] ?? "") : "";
		if (!$message) {
			$message = trim($response) ?: __('Karakeep returned an unexpected error.');
		}

		return [
			"ok" => false,
			"message" => sprintf('HTTP %d: %s', $status, $message),
		];
	}

	private function normalize_server_url(string $server_url) : string {
		$server_url = rtrim(trim($server_url), "/");

		if (!$server_url) {
			return "";
		}

		if (!preg_match("#/api/v[0-9]+$#i", $server_url)) {
			$server_url .= "/api/v1";
		}

		return $server_url;
	}

	private function normalize_tags(string $tags) : string {
		return implode(", ", $this->parse_tags($tags));
	}

	private function parse_tags(string $tags) : array {
		$result = [];

		foreach (explode(",", $tags) as $tag) {
			$tag = $this->normalize_text($tag);
			if ($tag !== "" && !in_array($tag, $result, true)) {
				$result[] = $tag;
			}
		}

		return $result;
	}

	private function normalize_text(string $value) : string {
		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
		$value = strip_tags($value);
		$value = preg_replace('/\s+/u', ' ', $value) ?: '';
		return trim($value);
	}

	private function print_json(array $payload) : void {
		header('Content-Type: application/json');
		print json_encode($payload);
	}

	function api_version() {
		return 2;
	}
}
