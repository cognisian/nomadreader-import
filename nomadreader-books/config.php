<?php

/**
 * Callback function to render the NomadReader config property
 */
function aws_access_token_cb($args) {
  $options = get_option(NR_OPT_AWS_TOKENS_CONFIG);

  // $name = NR_AWS_ACCESS_TOKEN;
  $name = NR_OPT_AWS_TOKENS_CONFIG . '[' . NR_AWS_ACCESS_TOKEN . ']';
  $value = isset($options[NR_AWS_ACCESS_TOKEN]) ?
    esc_attr($options[NR_AWS_ACCESS_TOKEN]) : '';

	printf(
    '<input type="text" name="%1$s" value="%2$s" size="42"
            placeholder="Your AWS Access Token" />',
    $name, $value
  );
}

/**
 * Callback function to render the NomadReader config property
 */
function aws_secret_token_cb($args) {
  $options = get_option(NR_OPT_AWS_TOKENS_CONFIG);
  $out = base64_decode($options[NR_AWS_SECRET_TOKEN]);

  $options[NR_AWS_SECRET_TOKEN] = decrypt_stuff($out);

  $name = NR_OPT_AWS_TOKENS_CONFIG . '[' . NR_AWS_SECRET_TOKEN . ']';
  $value = isset($options[NR_AWS_SECRET_TOKEN]) ?
    esc_attr($options[NR_AWS_SECRET_TOKEN]) : '';

	printf(
    '<input type="text" name="%1$s" value="%2$s" size="42"
            placeholder="Your AWS Secret Token" />',
    $name, $value
  );
}

/**
 * Callback function to render the NomadReader config property
 */
function aws_affiliate_tag_cb($args) {
  $options = get_option(NR_OPT_AWS_TOKENS_CONFIG);

  $name = NR_OPT_AWS_TOKENS_CONFIG . '[' . NR_AWS_AFFILIATE_TAG . ']';
  $value = isset($options[NR_AWS_AFFILIATE_TAG]) ?
    esc_attr($options[NR_AWS_AFFILIATE_TAG]) : '';

	printf(
    '<input type="text" name="%1$s" value="%2$s"
            placeholder="Your AWS Affiliate Tag" />',
    $name, $value
  );
}

/**
 * Callback function to render the NomadReader config property
 */
function nr_buy_button_text_cb($args) {
  $options = get_option(NR_OPT_AWS_TOKENS_CONFIG);

  $name = NR_OPT_AWS_TOKENS_CONFIG . '[' . NR_AMZN_BUY_BTN_TEXT . ']';
  $value = isset($options[NR_AMZN_BUY_BTN_TEXT]) ?
    esc_attr($options[NR_AMZN_BUY_BTN_TEXT]) : '';

	printf(
    '<input type="text" name="%1$s" value="%2$s"
            placeholder="Text to use for Buy Button" />',
    $name, $value
  );
}

/**
 * Generate the UI for the Amazon Token config section
 */
function ui_nr_aws_section_cb() {
  echo '<h5>Set the Amazon tokens to access the Amazon Product API
        </h5>';
}

/**
 * Generate the UI for the Amazon Affiliate text config section
 */
function ui_nr_aff_section_cb() {
  echo '<h5>Set the text to use for Amazon affiliate URLs and the buttons
        </h5>';
}

/**
 * Render the Settings page containing the
 */
function ui_nomadreader_amzn_tokens() {

  echo '<div class="wrap">
        <form method="post" action="options.php">
  ';

  settings_fields(NR_OPT_AWS_TOKENS_GRP);
  do_settings_sections(NR_OPT_AWS_TOKENS_GRP);

  echo submit_button();

  echo '</form></div>';
}

/**
 * Sanitize the input prior to saving as options
 */
function sanitize_text_array($value) {

  $opts = array(
		NR_AWS_ACCESS_TOKEN		=> '',
		NR_AWS_SECRET_TOKEN		=> '',
		NR_AWS_AFFILIATE_TAG	=> '',
		NR_AMZN_BUY_BTN_TEXT	=> '',
	);

  if (!is_array($value)) {
    return $opts;
    // add_settings_error('plugin:'.NR_OPT_AWS_TOKENS_GRP,
    //                    'invalid-string',
    //                    'Not a valid token');
  }

  foreach($value as $conf_name => $conf_value) {

    switch($conf_name) {

      case NR_AWS_SECRET_TOKEN:
        $opts[$conf_name] = base64_encode(encrypt_stuff($conf_value));
        break;

      case NR_AWS_ACCESS_TOKEN:
      case NR_AWS_AFFILIATE_TAG:
      case NR_AMZN_BUY_BTN_TEXT:
        $out = sanitize_text_field($conf_value);
        $opts[$conf_name] = $out;
        break;
    }
  }

  return $opts;
}

?>
