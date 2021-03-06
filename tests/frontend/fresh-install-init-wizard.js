require('geckodriver');
require('chromedriver');

const {Browser, By, Key, until, Condition} = require('selenium-webdriver');
const {suite} = require('selenium-webdriver/testing');
const assert = require('assert');

const InitWizardPage = require('./pages/init-wizard.js');

suite(function(env){

    describe('[Leyka] Fresh - Init Wizard', async function(){

        let driver, page;

        this.timeout(10000);

        before(async function(){

            driver = await env.builder().build();
            page = new InitWizardPage(driver);
            await page.open();

        });

        it('Admin logged in', async function(){

            await page.loginIntoWizardPage();

            let wizard_page = await driver.findElements(By.css('.wizard-init'));
            assert(wizard_page.length > 0);

        });

        it('Country selection step', async function(){

            await testNavigationAreaState('Ваши данные', false);

            await page.selectReceiverCountry('ru');
            await page.submitStep();

            let receiver_type_step_title = await driver.findElements(By.id('step-title-rd-receiver_type'));
            assert(receiver_type_step_title.length > 0);

        });

        it('Receiver type step - choosing "legal"', async function(){

            await testNavigationAreaState('Ваши данные', 'Получатель пожертвований');

            await page.selectReceiverType('legal');
            await page.submitStep();

        });

        it('Legal receiver data step - required fields validation testing', async function(){

            await testNavigationAreaState('Ваши данные', 'Ваши данные');

            let required_fields_to_check = ['org_full_name', 'org_face_fio_ip', 'org_state_reg_number'];

            await page.unsetRequiredFields(required_fields_to_check);
            await page.submitStep();

            let error_messages_shown = await page.requiredFieldsErrorsShown(required_fields_to_check);
            assert(error_messages_shown);

        });

        /** @todo Add this test after adding email format validation to the Wizards fields */
        // it('Legal receiver data step - email field validation testing', async function(){
        //
        //     let email_field_name = 'tech_support_email';
        //
        //     await page.setTextFieldValue(email_field_name, 'not#an-email');
        //     await page.submitStep();
        //
        //     let error_messages_shown = await page.emailFieldErrorShown(email_field_name);
        //     assert(error_messages_shown);
        //
        // });

        it('Legal receiver data step - OGRN masked field validation testing', async function(){

            let field_mask_correct = await page.checkFieldMask('org_state_reg_number', 'not#an-ogrn', '1023400056789');
            assert(field_mask_correct);

            await page.setTextFields({
                'org_full_name': 'Фонд помощи бездомным животным "Ак-Барсик"',
                'org_short_name': 'Фонд "Ак-Барсик"',
                'org_face_position': 'Директор',
                'org_face_fio_ip': 'Котов-Пёсов Аристарх Евграфович',
                'org_address': 'Москва, ул. Добра и Правды, д. 666, офис 13',
                'org_contact_person_name': 'Иван Петрович Сидоров',
                'tech_support_email': 'support@ak.barsik'
            });
            await page.setMaskedField('org_state_reg_number', '1023400056789');
            await page.setMaskedField('org_kpp', '780302015');
            await page.setMaskedField('org_inn', '4283256127');

            await page.submitStep();

        });

        it('Legal receiver bank essentials step - required fields validation testing', async function(){

            await testNavigationAreaState('Ваши данные', 'Банковские реквизиты');

            let required_fields_to_check = ['org_bank_name', 'org_bank_account', 'org_bank_corr_account', 'org_bank_bic'];

            await page.unsetRequiredFields(required_fields_to_check);
            await page.submitStep();

            let error_messages_shown = await page.requiredFieldsErrorsShown(required_fields_to_check);
            assert(error_messages_shown);

        });

        it('Legal receiver bank essentials step - bank account number masked field validation testing', async function(){

            let field_mask_correct = await page.checkFieldMask('org_bank_account', 'not#an-account-number', '40123840529627089012');
            assert(field_mask_correct);

            await page.setTextField('org_bank_name', 'Первый кредитный банк');
            await page.setMaskedField('org_bank_account', '40123840529627089012');
            await page.setMaskedField('org_bank_corr_account', '30101810270902010595');
            await page.setMaskedField('org_bank_bic', '044180293');

            await page.submitStep();

        });

        it('Oferta step - field testing', async function(){

            await testNavigationAreaState('Ваши данные', 'Оферта');

            let terms_option_id = 'terms_of_service_text';

            // Initial field value test:
            let terms_text_set = await page.isPlaceholderFieldTextSet(terms_option_id);
            assert(terms_text_set);

            let terms_text_includes_placeholders = await page.isFieldTextWithPlaceholders(terms_option_id);
            assert( !terms_text_includes_placeholders );

            await page.unsetPlaceholderFieldText(terms_option_id);

            await page.submitStep();

            let error_messages_shown = await page.requiredFieldsErrorsShown([terms_option_id]);
            assert(error_messages_shown);
            // Initial test finished

            // Re-test field value (after the incorrect submit):
            terms_text_set = await page.isPlaceholderFieldTextSet(terms_option_id);
            assert(terms_text_set);

            terms_text_includes_placeholders = await page.isFieldTextWithPlaceholders(terms_option_id);
            assert( !terms_text_includes_placeholders );
            // Re-test finished

            await page.submitStep();

        });

        it('Personal data step - terms field testing', async function(){

            await testNavigationAreaState('Ваши данные', 'Персональные данные');

            let terms_option_id = 'pd_terms_text';

            // Initial field value test:
            let terms_text_set = await page.isPlaceholderFieldTextSet(terms_option_id);
            assert(terms_text_set);

            let terms_text_includes_placeholders = await page.isFieldTextWithPlaceholders(terms_option_id);
            assert( !terms_text_includes_placeholders );

            await page.unsetPlaceholderFieldText(terms_option_id);

            await page.submitStep();

            let error_messages_shown = await page.requiredFieldsErrorsShown([terms_option_id]);
            assert(error_messages_shown);
            // Initial test finished

            // Re-test field value (after the incorrect submit):
            terms_text_set = await page.isPlaceholderFieldTextSet(terms_option_id);
            assert(terms_text_set);

            terms_text_includes_placeholders = await page.isFieldTextWithPlaceholders(terms_option_id);
            assert( !terms_text_includes_placeholders );
            // Re-test finished

            await page.submitStep();

        });

        it('"Your data" section - finishing step', async function(){

            await testNavigationAreaState('Ваши данные', true);
            await page.submitStep();

        });

        it('Diagnostic data agreement step - choosing "agree"', async function(){

            await testNavigationAreaState('Диагностические данные', false);

            let send_stats_is_agreed = page.statsFieldAgreed();
            assert(send_stats_is_agreed);

            await page.selectStatsAgreement('y');
            await page.submitStep();

        });

        it('Diagnostic data agreement step - "agree" chose', async function(){

            await testNavigationAreaState('Диагностические данные', true);

            let current_step_title = await page.getCurrentStepTitle();
            assert(current_step_title.includes('Спасибо'));

            await page.submitStep();

        });

        it('Main campaign settings step - required fields validation testing', async function(){

            await driver.sleep(500);
            await testNavigationAreaState('Настройка кампании', 'Основные сведения');

            let required_fields_to_check = ['campaign_title'];

            await page.unsetRequiredFields(required_fields_to_check);
            await page.submitStep();

            let error_messages_shown = await page.requiredFieldsErrorsShown(required_fields_to_check);
            assert(error_messages_shown);

            await page.setTextFields({
                'campaign_title': 'На уставную деятельность',
                'campaign_short_description': 'Краткое описание того, почему жертвовать нам важно и нужно, и на что мы потратим пожертвования. Можно одно-два предложения.'
            });
            await page.setMaskedField('campaign_target', '50000');

            await page.submitStep();

        });

        it('Campaign decoration step - thumbnail uploading field testing', async function(){

            await testNavigationAreaState('Настройка кампании', 'Оформление кампании');

            /**
             * @todo Check if campaign thumbnail already set. If it is, delete it from the medialib & upload anew.
             * Reason: FF Front-testing bugs with an unexpected alert.
             **/

            await page.setFileUploadField('campaign_photo', 'D:\\downloads\\browsers\\leyka-campaign-thumb-example.jpg');

            let campaign_preview_correct = await page.checkCampaignCardPreview();
            assert(campaign_preview_correct);

            await page.submitStep();

        });

        it('Donor thankful emails step - fields validation testing', async function(){

            await driver.sleep(500);
            await testNavigationAreaState('Настройка кампании', 'Благодарность донору');

            let field_option_id = 'email_thanks_text';

            // Initial field value test:
            let email_text_set = await page.isPlaceholderFieldTextSet(field_option_id);
            assert(email_text_set);

            let email_text_includes_placeholders = await page.isFieldTextWithPlaceholders(field_option_id, ['#SITE_NAME#']);
            assert( !email_text_includes_placeholders );

            await page.unsetPlaceholderFieldText(field_option_id);

            await page.submitStep();

            let error_messages_shown = await page.requiredFieldsErrorsShown([field_option_id]);
            assert(error_messages_shown);
            // Initial test finished

            // Re-test field value (after the incorrect submit):
            email_text_set = await page.isPlaceholderFieldTextSet(field_option_id);
            assert(email_text_set);

            email_text_includes_placeholders = await page.isFieldTextWithPlaceholders(field_option_id, ['#SITE_NAME#']);
            assert( !email_text_includes_placeholders );
            // Re-test finished

            await page.submitStep();

        });

        it('Settings complete step - campaign slug field testing', async function(){

            await testNavigationAreaState('Завершение настройки', true);

            let campaign_default_slug = 'na-ustavnuyu-deyatelnost',
                campaign_url_correct = await page.checkCampaignPermalinkDisplayed(campaign_default_slug);
            assert(campaign_url_correct);

            await page.openCampaignSlugEditForm();

            let campaign_slug_edit_form_opened = await page.campaignSlugEditFormInState('opened');
            assert(campaign_slug_edit_form_opened);

            await page.closeCampaignSlugEditForm('cancel');

            let campaign_slug_edit_form_closed = await page.campaignSlugEditFormInState('closed');
            await assert(campaign_slug_edit_form_closed);

            await driver.sleep(250);

            await page.changeCampaignSlugTo('main-#$%^&!-campaign');
            await driver.sleep(250);

            campaign_url_correct = await page.checkCampaignPermalinkDisplayed('main-campaign');
            assert(campaign_url_correct);

            await driver.sleep(250);

            await page.changeCampaignSlugTo(campaign_default_slug); // To easy up the re-testing
            await driver.sleep(250);

            let campaign_shortcode_correct = await page.checkCampaignShortcode();
            assert(campaign_shortcode_correct);

        });

        it('Settings complete step - campaign page link testing', async function(){

            let campaign_page_url_correct = page.checkCampaignFrontPageLink();
            assert(campaign_page_url_correct);

            let driver_capabilities = await driver.getCapabilities();

            // ATM Firefox driver explicitly doesn't support tabs via WindowHandles at all :(
            if(driver_capabilities.getBrowserName() === 'chrome') {

                await page.openCampaignFrontPage();

                let campaign_page_title_correct = await page.checkCampaignFrontPageTitle();
                assert(campaign_page_title_correct);

                let campaign_page_url_correct = await page.checkCampaignFrontPageUrl();
                assert(campaign_page_url_correct);

                let campaign_view_correct = await page.checkCampaignCardDisplay();
                assert(campaign_view_correct);

                await page.closeCampaignFrontPage();
                await page.returnToMainPage();

            }

        });

        it('Settings complete step - quitting the wizard testing', async function(){

            await page.quitWizard();

            let default_settings_page_correct = page.checkDefaultSettingsPage();
            assert(default_settings_page_correct);

        });

        after(async function(){
            await driver.quit();
        });

        async function testNavigationAreaState(section_name, step_name) {

            let is_navigation_area_correct = await page.isNavigationAreaInState(section_name, step_name);
            assert(is_navigation_area_correct);

        }

    });
});