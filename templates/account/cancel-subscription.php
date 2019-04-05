<?php
/**
 * The template for displaying leyka persistent campaign
 *
 * @link https://leyka.te-st.ru/campaign/demo-kampaniya/
 *
 * @package Leyka
 * @since 1.0.0
 */

$recurring_subscriptions = leyka_get_init_recurring_donations(false, true);

include(LEYKA_PLUGIN_DIR . 'templates/account/header.php'); ?>

<div id="content" class="site-content leyka-campaign-content">
    
    <section id="primary" class="content-area">
        <main id="main" class="site-main">
            <div class="entry-content">

                <div id="leyka-pf-" class="leyka-pf leyka-pf-star">
                    <div class="leyka-account-form leyka-unsubscribe-campains-forms">
        
                        <form class="leyka-screen-form leyka-unsubscribe-campains-form">
                            
                            <?php if($recurring_subscriptions) {?>
                            
                            <h2><?php esc_html_e('Which campaign you want to unsubscibe from?', 'leyka');?></h2>
                            
                            <div class="list">
                                <div class="items">
                                	<?php foreach($recurring_subscriptions as $init_donation) {
                                	    $donation_campaign = new Leyka_Campaign($init_donation->campaign_id);
                                	?>
                                    <div class="item">
                                        <span class="campaign-title"><?php echo $init_donation->campaign_payment_title;?></span>
                                        <a data-campaign-id="<?php echo $init_donation->campaign_id;?>" href="<?php echo $donation_campaign->permalink;?>" class="action-disconnect"><?php esc_html_e('Disable');?></a>
                                    </div>
                                	<?php } ?>
                                </div>
                            </div>
                            
                            <?php } else {?>
                            
                            <h2><?php esc_html_e('You have no active recurring subscriptions.', 'leyka');?></h2>
                            
                            <?php } ?>
        
                            <div class="leyka-star-submit">
                                <a href="<?php echo site_url('/donor-account/');?>" class="leyka-star-single-link"><?php esc_html_e('To main' , 'leyka');?></a>
                            </div>
        
                        </form>
                        
                        
                        <form class="leyka-screen-form leyka-cancel-subscription-form">
                            
                            <h2><?php esc_html_e('We will be grateful if you share why you decided to cancel the subscription?', 'leyka');?></h2>
                            
                            <div class="limit-width">
                                <div class="leyka-cancel-subscription-reason">
                                	<?php foreach(leyka_get_cancel_subscription_reasons() as $reason_value => $reason_text) {?>
                                    <span>
                                        <input type="checkbox" name="leyka_cancel_subscription_reason[]" id="leyka_cancel_subscription_reason_<?php echo $reason_value;?>" class="required" value="<?php echo $reason_value;?>">
                                        <label for="leyka_cancel_subscription_reason_<?php echo $reason_value;?>"><?php echo $reason_text;?></label>
                                    </span>
                                	<?php }?>
                                </div>
                                
                                <div class="section unsubscribe-comment">
                                    <div class="section__fields donor">
                                        <?php $field_id = 'leyka-'.wp_rand();?>
                                        <div class="donor__textfield donor__textfield--comment">
                                            <div class="leyka-star-field-frame">
                                                <label for="<?php echo $field_id;?>">
                                                    <span class="donor__textfield-label leyka_donor_custom_reason-label"><?php echo __('Your reason', 'leyka');?></span>
                                                </label>
                                                <textarea id="<?php echo $field_id;?>" class="leyka-donor-comment" name="leyka_donor_custom_reason"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        
                                <div class="leyka-hidden-controls">
                                	<input type="hidden" name="leyka_campaign_id" value="">
                                	<input type="hidden" name="leyka_campaign_permalink" value="">
                                	<?php wp_nonce_field( 'leyka_cancel_subscription' );?>
                                </div>
                                
                                <div class="leyka-star-field-error-frame">
                                    <span class="donor__textfield-error choose-reason"><?php _e('Choose unsubscription reason, please', 'leyka');?></span>
                                    <span class="donor__textfield-error give-details"><?php _e('Give some details about your reason', 'leyka');?></span>
                                </div>
                                
                        
                                <div class="leyka-star-submit double">
                                    <a href="<?php echo site_url('/donor-account/');?>" class="leyka-star-btn leyka-do-not-unsubscribe"><?php esc_html_e('Do not unsubscribe', 'leyka');?></a>
                                    <input type="submit" name="unsubscribe" class="leyka-star-btn secondary last" value="<?php esc_html_e('Continue', 'leyka');?>">
                                </div>
                                
                            </div>
                        
                        </form>
                        
                        
                        <form class="leyka-screen-form leyka-confirm-unsubscribe-request-form">
                        
                            <h2><?php esc_html_e('Disable subscription?', 'leyka');?></h2>
                            
                            <div class="form-controls">
                                <p><?php esc_html_e('We were able to do a lot with the help of your donations. Without your support, it will be harder for us to achieve results. It is a pity that you unsubscribe!', 'leyka');?></p>
                                
                                <div class="form-message"></div>
                                
                                <div class="leyka-star-submit double confirm-unsubscribe-submit">
                                    <a href="#" class="leyka-star-btn leyka-do-not-unsubscribe"><?php esc_html_e('Do not cancel', 'leyka');?></a>
                                    <input type="submit" name="unsubscribe" class="leyka-star-btn secondary last" value="<?php esc_html_e('Cancel subscription', 'leyka');?>">
                                </div>
                            </div>
                        
                            <div class="leyka-form-spinner">
                            	<?php echo leyka_get_ajax_indicator();?>
                            </div>
                        
                        </form>
                        
                        
                        <form class="leyka-screen-form leyka-confirm-go-resubscribe-form">
                        
                            <h2><?php esc_html_e('Canceling subscription', 'leyka');?></h2>
                            
                            <div class="form-controls">
                                <p><?php esc_html_e('Now we will cancel the current subscription and then you can, if you wish, subscribe again to a more convenient amount or method of donation.', 'leyka');?></p>
                                
                                <div class="form-message"></div>
                                
                                <div class="leyka-star-submit double confirm-unsubscribe-submit">
                                    <a href="#" class="leyka-star-btn leyka-do-not-unsubscribe"><?php esc_html_e('Do not cancel', 'leyka');?></a>
                                    <input type="submit" name="unsubscribe" class="leyka-star-btn secondary last" value="<?php esc_html_e('Cancel subscription', 'leyka');?>">
                                </div>
                            </div>
                        
                            <div class="leyka-form-spinner">
                            	<?php echo leyka_get_ajax_indicator();?>
                            </div>
                        
                        </form>
                        
                        
                        <form class="leyka-screen-form leyka-unsubscribe-request-accepted-form">
                        
                            <h2><?php esc_html_e('Your request to unsubscribe accepted', 'leyka');?></h2>
                            
                            <p><?php esc_html_e('The subscription will be disabled within 3 days', 'leyka');?></p>
                            
                            <div class="leyka-star-submit">
                            	<a href="<?php echo site_url('/donor-account/');?>" class="leyka-star-single-link"><?php esc_html_e('To main' , 'leyka');?></a>
                            </div>
                                
                        </form>
                        

                    </div>
                </div>
                
            </div>

        </main>
    </section>

</div>

<?php get_footer(); ?>