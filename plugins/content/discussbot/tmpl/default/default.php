<?php if($this->params->get('show_posts')) : ?>
    <div class="jfusionPostHeader"><?php echo $this->params->get('post_header'); ?></div>

    <div class="jfusionPostArea" id="jfusionPostArea">
        <?php require(DISCUSSION_TEMPLATE_PATH.'default_posts.php'); ?>
    </div>

    <?php if(!empty($this->output['post_pagination'])) : ?>
        <br /><?php echo $this->output['post_pagination']; ?>
    <?php endif; //post_pagination ?>
<?php endif; //show_posts ?>

<?php if($this->output['show_reply_form']): ?>
    <div class="jfusionQuickReply" id="jfusionQuickReply">
        <?php if ($this->output['show_reply_form']) : ?>
            <div id="jfusionMessageArea"><div id="jfusionMessage"></div></div>
            <div id="jfusionErrorArea"><div id="jfusionErrorMessage"></div></div>
            <div class="jfusionQuickReplyHeader"><?php echo $this->params->get('quick_reply_header'); ?></div>
            <?php echo $this->output['reply_form']; ?>

            <div style="width:99%; text-align:right;">
                <?php if($this->params->get('enable_ajax',1)) :?>
                    <input type="button" class="button" id="submitpost" value='<?php echo JText::_('SUBMIT'); ?>'/>
                <?php else: ?>
                    <input type="button" class="button" id="submitpost" onclick="$('jfusionQuickReply<?php echo $this->article->id; ?>').submit();" value='<?php echo JText::_('SUBMIT'); ?>'/>
                <?php endif; //enable_ajax ?>
            </div>
        <?php elseif (!empty($this->output['reply_form_error'])) : ?>
            <?php echo $this->output['reply_form_error']; ?>
        <?php endif; //show_reply_form ?>
    </div>
<?php endif; //show_quickreply ?>