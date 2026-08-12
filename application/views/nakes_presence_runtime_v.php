<?php if (!empty($nakes_presence_bootstrap['enabled'])) : ?>
	<script>window.DoclincNakesPresenceConfig = <?= json_encode($nakes_presence_bootstrap, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
	<script src="<?= html_escape(doclinc_nakes_presence_asset_url()); ?>"></script>
<?php endif; ?>
