<p <?php if (!$label): ?> hidden <?php endif; ?> >
<?php if ($label): ?>
  <label for="<?php echo htmlspecialchars ($name); ?>"><?php echo htmlspecialchars ($label); ?>:</label>
<?php endif; ?>
  <input type="text" name="<?php echo htmlspecialchars ($name); ?>" value="<?php echo htmlspecialchars ($value); ?>" <?php if (!$label): ?> readonly="readonly" <?php endif; ?> />
</p>
