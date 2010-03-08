<div class="<?php echo $type ?>">
<?php if ( ! is_array($messages)): ?>
	<p>
		<?php echo __($messages) ?>
	</p>
<?php else: ?>
	<ul>
	<?php foreach ($messages as $message): ?>
		<li><?php echo __($message) ?></li>
	<?php endforeach ?>
	</ul>
<?php endif; ?>
</div>