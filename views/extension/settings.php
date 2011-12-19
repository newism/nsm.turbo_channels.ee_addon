<?php

/**
 * View for Control Panel Settings Form
 * This file is responsible for displaying the user-configurable settings for the NSM Multi Language extension in the ExpressionEngine control panel.
 *
 * !! All text and textarea settings must use form_prep($value); !!
 *
 * @package Nsm_turbo_channels
 * @version 1.0.0
 * @author Leevi Graham <http://leevigraham.com.au>
 * @copyright Copyright (c) 2007-2010 Newism
 * @license Commercial - please see LICENSE file included with this distribution
 **/

$EE =& get_instance();

?>

<div class="mor">
	<?= form_open(
			'C=addons_extensions&M=extension_settings&file=' . $addon_id,
			array('id' => $addon_id . '_prefs'),
			array($input_prefix."[enabled]" => TRUE)
		)
	?>

	<!-- 
	===============================
	Alert Messages
	===============================
	-->

	<?php if($error) : ?>
		<div class="alert error"><?php print($error); ?></div>
	<?php endif; ?>

	<?php if($message) : ?>
		<div class="alert success"><?php print($message); ?></div>
	<?php endif; ?>
	
	<div class="tg">
		<h2>Enable?</h2>
		<table>
			<tbody>
				<tr>
					<th scope="row">Enable?</th>
					<td><?= $EE->nsm_turbo_channels_helper->yesNoRadioGroup($input_prefix."[enabled]", $data["enabled"]); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="tg">
		<h2>Playa short-tags</h2>
		<table>
			<tbody>
				<tr>
					<th scope="row">Override Playa short-tags?</th>
					<td>
						<?= $EE->nsm_turbo_channels_helper->yesNoRadioGroup($input_prefix."[playa][do_override]", $data['playa']['do_override']); ?>
					</td>
				</tr>
				<tr>
					<th scope="row">Default disable parameter</th>
					<td>
						<input
							type="text"
							name="<?= $input_prefix ?>[playa][disable_param]"
							value="<?= $data['playa']['disable_param'] ?>"
						/>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="actions">
		<input type="submit" class="submit" value="<?php print lang('save_extension_settings') ?>" />
	</div>

	<?= form_close(); ?>
</div>