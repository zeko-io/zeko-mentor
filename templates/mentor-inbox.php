<?php
/**
 * Mentor inbox template.
 *
 * @package Zeko_Mentor
 */

?>
<div class="zm-inbox" style="display:flex;gap:0;min-height:500px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
	<div class="zm-conversations" style="width:320px;border-right:1px solid #e5e7eb;overflow-y:auto;">
		<div style="padding:16px;border-bottom:1px solid #e5e7eb;font-weight:600;">
			<?php esc_html_e( 'Conversations', 'zeko-mentor' ); ?>
		</div>
		<div id="zm-conversation-list"></div>
		<div id="zm-inbox-loading" style="padding:24px;text-align:center;color:#6b7280;">
			<?php esc_html_e( 'Loading conversations...', 'zeko-mentor' ); ?>
		</div>
	</div>
	<div class="zm-thread" style="flex:1;display:flex;flex-direction:column;">
		<div id="zm-thread-empty" style="flex:1;display:flex;align-items:center;justify-content:center;color:#9ca3af;">
			<?php esc_html_e( 'Select a conversation to start chatting.', 'zeko-mentor' ); ?>
		</div>
		<div id="zm-thread-content" style="flex:1;display:none;flex-direction:column;">
			<div id="zm-thread-header" style="padding:12px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;"></div>
			<div id="zm-thread-messages" style="flex:1;overflow-y:auto;padding:16px;"></div>
			<div style="padding:12px;border-top:1px solid #e5e7eb;display:flex;gap:8px;">
				<textarea id="zm-msg-input" rows="2" style="flex:1;border:1px solid #d1d5db;border-radius:6px;padding:10px;resize:none;font-family:inherit;font-size:0.95em;" placeholder="<?php esc_attr_e( 'Type your message...', 'zeko-mentor' ); ?>"></textarea>
				<button type="button" class="zm-btn zm-btn-primary" id="zm-msg-send"><?php esc_html_e( 'Send', 'zeko-mentor' ); ?></button>
			</div>
		</div>
	</div>
</div>
