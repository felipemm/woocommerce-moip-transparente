<?php
/*
Plugin Name: WooCommerce Moip Checkout Transparente
Plugin URI: http://wooplugins.com.br
Description: Adiciona o gateway de pagamento do MoIP Checkout Transparente no WooCommerce
Version: 2.0
Author: F2M Tecnologia
Author URI: http://www.f2mtecnologia.com.br
License: Commercial
Requires at least: 3.4
Tested up to: 3.5.2
*/

require_once "moip-api/lib/Moip.php";
require_once "moip-api/lib/MoipClient.php";
require_once "moip-api/lib/MoipStatus.php";



//hook to include the payment gateway function
add_action('plugins_loaded', 'f2m_gateway_moip_transparente');

//======================================================================
//hook function for MOIP PAYMENT GATEWAY
//======================================================================
function f2m_gateway_moip_transparente(){
	
	//check if woocommerce is installed
	if ( !class_exists( 'WC_Payment_Gateway' ) || !class_exists( 'WC_Order_Item_Meta' ) ) {
		add_action( 'admin_notices', 'cielodireto_woocommerce_fallback_notice' );
		return;
	}
	
	//======================================================================
	//Add the gateway to WooCommerce
	//======================================================================
	function add_moip_gateway( $methods ) {
		$methods[] = 'F2M_Moip_Transparente';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_moip_gateway' );



	//======================================================================
	//Moip payment gateway class
	//======================================================================
	class F2M_Moip_Transparente extends WC_Payment_Gateway {

		//======================================================================
		// object constructor function
		//======================================================================
		public function __construct() {
			global $woocommerce;

      		$this->id                 = 'moiptransparente';
			$this->icon               = apply_filters('woocommerce_'.$this->id.'_icon', $url = plugin_dir_url(__FILE__).$this->id.'.png');
      		$this->has_fields         = false;
            $this->method_title       = __('Moip Transparente', 'woothemes');
            $this->method_description = __('Método de pagamento pelo MoIP (via checkout transparente).', 'woothemes');
			$this->notify_url         = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', get_class($this), home_url( '/' ) ) );


			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title           = $this->get_option('title');
			$this->email           = $this->get_option('email');
			$this->user            = $this->get_option('user');
			$this->token           = $this->get_option('token');
			$this->key             = $this->get_option('key');
			$this->token_validator = $this->get_option('token_validator');
			$this->store_nickname  = $this->get_option('store_nickname');
			$this->debug           = $this->get_option('debug');
			$this->allow_parcel    = $this->get_option('allow_parcel');
			$this->parcel_interest = $this->get_option('parcel_interest');
			$this->enable_credito  = $this->get_option('enable_credito');
			$this->enable_debito   = $this->get_option('enable_debito');
			$this->enable_boleto   = $this->get_option('enable_boleto');
			$this->testmode        = $this->get_option('testmode');

			// Logs
			if ($this->debug=='yes') $this->log = $woocommerce->logger();

			// Payment Gateway Actions
			add_action('woocommerce_update_options_payment_gateways_'.$this->id, array(&$this, 'process_admin_options'));
      		add_action('woocommerce_receipt_'.$this->id, array(&$this, 'receipt_page'));
			add_action('woocommerce_api_'.strtolower(get_class( $this )), array( $this, 'check_ipn_response' ) );
			add_action('admin_notices', array(&$this, 'cielodireto_check_missing_fields_message'));
			add_action('valid-moip-standard-ipn-request', array(&$this, 'successful_request'));


			if ( !$this->is_valid_for_use() ) $this->enabled = false;
		} //End of __construct()



		//======================================================================
		//Check if this gateway is enabled and available in the user's country
		//======================================================================
  		function is_valid_for_use() {
      		if (!in_array(get_option('woocommerce_currency'), array('BRL'))){
				if (!isset($this->credential) || $this->credential == ''){
					if (!isset($this->token) || $this->token == ''){
						if (!isset($this->user) || $this->user == ''){
							if (!isset($this->email) || $this->email == ''){
								return false;
							}
						}
					}
				}
			}
      		return true;
  		} //Fim da função is_valid_for_use



		//======================================================================
		//Initialise Gateway Settings Form Fields
		//======================================================================
		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Habilita/Desabilita', 'woothemes' ),
					'type' => 'checkbox',
					'label' => __( 'Habilita ou não o portal de pagamento do MoIP.', 'woothemes' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Título', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'Títúlo que o usuário verá durante o checkout na escolha da forma de pagamento', 'woothemes' ),
					'default' => __( 'Pague com MoIP', 'woothemes' )
				),
				'email' => array(
					'title' => __( 'E-Mail', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'E-mail da sua conta do MoIP onde será recebido os pagamentos', 'woothemes' )
				),
				'user' => array(
					'title' => __( 'Usuário', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'Usuário da sua conta do MoIP onde será recebido os pagamentos', 'woothemes' )
				),
				'token' => array(
					'title' => __( 'Token', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'Token gerado pelo MoIP para uso da API de pagamento.', 'woothemes' )
				),
				'key' => array(
					'title' => __( 'Chave', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'Chave gerada pelo MoIP para uso da API de pagamento.', 'woothemes' )
				),
				'token_validator' => array(
					'title' => __( 'Token Validador', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'Define uma chave de validação para o NASP (maior segurança).', 'woothemes' )
				),
				'store_nickname' => array(
					'title' => __( 'Apelido da Loja', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'Nome da loja que aparecerá no carrinho do MoIP.', 'woothemes' ),
					'default' => 'Nome da Loja'
				),
				'enable_credito' => array(
					'title' => __( 'Habilitar cartão de crédito?', 'woothemes' ),
					'type' => 'checkbox',
					'default' => 'yes'
				),
				'enable_debito' => array(
					'title' => __( 'Habilita débito em conta?', 'woothemes' ),
					'type' => 'checkbox',
					'default' => 'yes'
				),
				'enable_boleto' => array(
					'title' => __( 'Habilita boleto bancário?', 'woothemes' ),
					'type' => 'checkbox',
					'default' => 'yes'
				),
				'allow_parcel' => array(
					'title' => __( 'Habilita parcelamento?', 'woothemes' ),
					'type' => 'checkbox',
					'default' => 'yes'
				),
				'parcel_interest' => array(
					'title' => __( 'Valor do juros de parcelamento (ex.: 1.99 -> 1,99% a.m.)', 'woothemes' ),
					'type' => 'text',
					'default' => '0'
				),
				'testmode' => array(
					'title' => __( 'MoIP Sandbox', 'woothemes' ),
					'type' => 'checkbox',
					'label' => __( 'Habilita o uso do MoIP para desenvolvimento', 'woothemes' ),
					'default' => 'yes'
				),
				'debug' => array(
					'title' => __( 'Debug', 'woothemes' ),
					'type' => 'checkbox',
					'label' => __( 'Habilita a escrita de log para debug (<code>woocommerce/logs/'.$this->id.'.txt</code>)', 'woothemes' ),
					'default' => 'yes'
				)
			);
		} // End of init_form_fields()



		//======================================================================
		//Admin Panel Options
		//Options for bits like 'title' and availability on a country-by-country basis
		//======================================================================
		public function admin_options() {
			?>
				<h3><?php echo $this->method_title; ?></h3>
				<p><?php _e('Opção para pagamento através do Moip Checkout Transparente', 'woothemes'); ?></p>
				<table class="form-table">
					<?php
						// Generate the HTML For the settings form.
						$this->generate_settings_html();
					?>
				</table><!--/.form-table-->
			<?php
		} // End admin_options()



    	//---------------------------------------------------------------------------------------------------
  		//Função: payment_fields
  		//Descrição: Exibe a Mensagem ao selecionar a forma de pagamento se ela estiver definida
  		//---------------------------------------------------------------------------------------------------
  		function payment_fields() {
      		if ($this->description)
      			echo wpautop(wptexturize($this->description));
    	} //Fim da função payment_fields



    	//---------------------------------------------------------------------------------------------------
  		//Função: process_payment
  		//Descrição: processa o pagamento e retorna o resultado
  		//---------------------------------------------------------------------------------------------------
		function process_payment( $order_id ) {
      		$order = &new WC_Order( $order_id );

      		return array(
        		'result'    => 'success',
        		'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
      		);
    	} //Fim da função process_payment



    	//---------------------------------------------------------------------------------------------------
  		//Função: receipt_page
  		//Descrição: Página final antes de redirecionar para a página de pagamento do cielodireto
  		//---------------------------------------------------------------------------------------------------
    	function receipt_page( $order ) {
			echo '<p>'.__('Obrigado pelo seu pedido. Por favor clique em Pagar com MoIP para finalizar a transação.', 'woothemes').'</p>';
			echo '<p>'.__('* A sua transação será processada diretamente através da Moip Pagamentos S/A. Nenhuma informação sensível é compartilhada com o site.', 'woothemes').'</p>';
			echo $this->generate_moip_form($order);
    	} //Fim da função receipt_page




		//======================================================================
		//generate the form to send to moip
		//======================================================================
		function generate_moip_form($order_id){
			global $woocommerce;
			$order = &new WC_Order( $order_id );
			$moip = new Moip();

			//set the environment
			$moip->setEnvironment($this->testmode == 'yes' ? 'test' : 'prod');
			
			//set credentials and receiver information
			$moip->setCredential(array('key' => $this->key,'token' => $this->token));
			
			//$moip->setReceiver($this->email);
			$moip->setReceiver($this->user);
			
			//$moip->setNotificationURL('<![CDATA['.htmlspecialchars($this->get_return_url($order)).']]>');
			$moip->setNotificationURL(htmlspecialchars($this->notify_url));
			$moip->setReturnURL(htmlspecialchars($this->get_return_url($order)));

			//set order information
			$moip->setUniqueID($order->id ."-".microtime());
			$moip->setValue(number_format($order->get_total(),2,".",""));
			$moip->setReason('Compra feita: '. $this->store_nickname);
			
			//put product description in the payment
			foreach ($order->get_items() as $item){
				$moip->addMessage(($item['name'] . ' - ' . $item['qty'] . ' Un.'));
			}

			//set payment methods that will be available for the customer
			$moip->addPaymentWay('creditCard');
			$moip->addPaymentWay('billet');
			$moip->addPaymentWay('financing');
			$moip->addPaymentWay('debit');
			$moip->addPaymentWay('debitCard');


			$moip->setPayer(
				array(
					'name' => $order->billing_first_name . " " . $order->billing_last_name,
					'email' => $order->billing_email,
					'payerId' => $order->billing_email,
					'billingAddress' => array(
						'address' => $order->billing_address_1,
						'number' => get_post_meta($order->id, '_billing_number', true),
						'complement' => $order->billing_address_2,
						'neighborhood' => get_post_meta($order->id, '_billing_district', true),
						'city' => $order->billing_city,
						'state' => $order->billing_state,
						'country' => $order->billing_country,
						'zipCode' => $order->billing_postcode,
						'phone' => $order->billing_phone
					)
				)
			);


			//valida as informações para ser enviada para o xml
			$moip->validate('Identification');

			//armazena o xml no log para validação
			if ($this->debug=='yes') $this->log->add($this->id, 'XML enviado para o MoIP: '. $moip->getXML());

			//envia o xml para o moip
			$moip->send();

			//armazena as informações de resposta na variável;
			$resposta = $moip->getAnswer();

			//valida a resposta
			if($resposta->response){
				if ($this->debug=='yes') $this->log->add($this->id, 'XML enviado com sucesso. TOKEN: '. $resposta->token);

				//parcelamento
				if($this->allow_parcel=='yes'){
					$juros = number_format($this->parcel_interest, 2, ".", "");
					$parcelas = $moip->queryParcel($this->user, '12', $juros, (number_format($order->get_total(),2,".","")));
					if(count($parcelas['installment']) > 0){
						$divParcela = "<select id='numParcelas' onchange='changePaymentType(\"CartaoCredito\");'>";
						foreach($parcelas['installment'] as $key => $value){
							if ($key == '1') {
								$divParcela .= "<option selected='selected' value='".$key."'>".$key ." x R$ ".number_format($value['value'],2,",","")." (R$ ".number_format($value['total'],2,",","").")</option>";
							} else {
								$divParcela .= "<option value='".$key."'>".$key ." x R$ ".number_format($value['value'],2,",","")." (R$ ".number_format($value['total'],2,",","").")</option>";
							}
						}
						$divParcela .= "</select>";
					} else {
						$divParcela = "<select id='numParcelas' onchange='changePaymentType(\"CartaoCredito\");'>";
						$divParcela .= "<option selected='selected' value='1'>1 x R$ ".(number_format($order->get_total(),2,".",""))." (R$ ".(number_format($order->get_total(),2,".","")).")</option>";
						$divParcela .= "</select>";
					}
				} else {
					$divParcela = "<select id='numParcelas' onchange='changePaymentType(\"CartaoCredito\");'>";
					$divParcela .= "<option selected='selected' value='1'>1 x R$ ".(number_format($order->get_total(),2,".",""))." (R$ ".(number_format($order->get_total(),2,".","")).")</option>";
					$divParcela .= "</select>";
				}

				//add the javascript and html code necessary to do the checkout on the client side
				$payment_form = "
						<script type='text/javascript' charset='utf-8' src='".plugin_dir_url(__FILE__)."functions.js'></script>
						<script type='text/javascript'><!--
							plugin_url = '".$woocommerce->plugin_url()."';
							cancel_order_url = '".$order->get_cancel_order_url()."';
							return_url = '".$this->get_return_url($order)."';
						--></script>
						<div id='MoipWidget'
							data-token='".$resposta->token."'
							callback-method-success='funcaoSucesso'
							callback-method-error='funcaoFalha'>
						</div>
						<div id='payment_form'>
							<div id='paymentResult'></div>
							<div id='paymentWaiting'></div>
							<div id='paymentOptions'>";
				if($this->enable_credito == 'yes'){
					$payment_form .= "
								<input type='radio' name='paymentType' id='paymentType' value='CartaoCredito'  onclick='changePaymentType(this.value)' /> Cartão de Crédito (Visa, Mastercard, Diners, Hipercard, American Express)<br>
								<div id='paymentFormCredito' style='display: none; margin: 5 5 5 5;'>
									<table>
										<tr>
											<td>Nome Portador</td>
											<td><input type='text' id='nomePortador' value='".$order->billing_first_name . " " . $order->billing_last_name."' /> (como consta no cartão)</td>
										</tr>
										<tr>
											<td>Número do Cartão</td>
											<td>
												<select id='tipoCartao' onchange='changeCreditCardType(this);'>
													<option value='AmericanExpress'>American Express</option>
													<option value='Diners'>Diners</option>
													<option value='Mastercard'>Mastercard</option>
													<option value='Hipercard'>Hipercard</option>
													<option value='Visa' selected='selected'>Visa</option>
												</select>
												<input type='text' id='numeroCartao' value='' maxlength='16' size='25' onKeyPress='MascaraCartao(this, event);' />
											</td>
										</tr>
										<tr>
											<td>Código de Segurança</td>
											<td><input type='text' id='codigoSeguranca' value='' maxlength='3'  size='5' /></td>
										</tr>
										<tr>
											<td>Data Validade</td>
											<td><input type='text' id='dataValidade' onKeyPress='MascaraVencimento(this, event);' maxlength='7' onBlur= 'ValidaVencimento(this);' /> MM/AAAA</td>
										</tr>
										<tr>
											<td>Opções de Parcelamento</td>
											<td>$divParcela</td>
										</tr>
										<tr>
											<td>Data de Nascimento </td>
											<td><input type='text' id='dataNascimento' value='".get_post_meta($order->id, '_billing_nascimento', true)."' onKeyPress='MascaraData(this, event);' maxlength='10' onBlur= 'ValidaData(this);' /> DD/MM/AAAA</td>
										</tr>
										<tr>
											<td>Telefone</td>
											<td><input type='text' id='Telefone' value='".$order->billing_phone."' maxlength='14'  onKeyDown='mascara(this, mtel);'/> (99)9999-9999</td>
										</tr>
										<tr>
											<td>CPF</td>
											<td><input type='text' id='cpf' value='".get_post_meta($order->id, '_billing_cpf', true)."' onBlur='ValidarCPF(this);' onKeyPress='MascaraCPF(this, event);' maxlength='14' /></td>
										</tr>
									</table>
								</div>";
				} else {
					$payment_form .= "<div id='paymentFormCredito'></div>";
				}

				if($this->enable_debito == 'yes'){
					$payment_form .= "
								<input type='radio' name='paymentType' id='paymentType' value='DebitoBancario' onclick='changePaymentType(this.value)' /> Débito automático em conta (BB, Bradesco, Banrisul, Itaú)<br>
								<div id='paymentFormDebito' style='display: none; margin: 5 5 5 5;'>
									<table>
									<tr>
										<td>Selecione o banco: </td>
										<td>
											<select id='tipoBancoDebito'>
												<option value='BancoDoBrasil'>Banco do Brasil</option>
												<option value='Bradesco'>Bradesco</option>
												<option value='Banrisul'>Banrisul</option>
												<option value='Itau' selected='selected'>Itaú</option>
											</select>
										</td>
									</tr>
									</table>
								</div>";
				} else {
					$payment_form .= "<div id='paymentFormDebito'></div>";
				}

				if($this->enable_boleto == 'yes'){
					$payment_form .= "<input type='radio' name='paymentType' id='paymentType' value='BoletoBancario' onclick='changePaymentType(this.value)' /> Boleto Bancário <br><br>";
				}

				$payment_form .= "
							</div>
							<div id='paymentButtons' style='display: none;'>
								<button id='paymentButton' class='button' onclick='executePayment()' >Pagar com MoIP</button>
								<button id='cancelButton'  class='button' onclick='cancelPayment()'  >Cancelar Pedido</button>
								<button id='confirmOrder'  class='button' onclick='confirmOrder()'   style='display: none;'>Finalizar Pedido</button>
								<button id='printBoleto'   class='button' onclick='printBoleto()'    style='display: none;'>Imprimir Boleto</button>
							</div>
						</div>
						<div style='clear:both'></div>
						<script type='text/javascript' src='".($this->testmode == 'yes' ? 'https://desenvolvedor.moip.com.br/sandbox' : 'https://www.moip.com.br')."/transparente/MoipWidget-v2.js' charset='utf-8'></script>";
			} else {
				//houve um erro na transação, lançar no log
				if ($this->debug=='yes') $this->log->add($this->id, 'Houve um erro ao processar o XML: '. $resposta->error);
				$payment_form = utf8_encode($resposta->error);
				$payment_form .= $order->billing_number;
				$payment_form .= 'felipe';
				$payment_form .= "<br><button id='cancelButton'  class='button' onclick='window.location = \"".esc_url( $order->get_cancel_order_url() )."\";'  style='display: inline;'>Cancelar Pedido</button>";
			}

			if ($this->debug=='yes') $this->log->add($this->id, "Pedido gerado com sucesso. Abaixo código HTML do formulário:");
			if ($this->debug=='yes') $this->log->add($this->id, $payment_form);

			return $payment_form;
		} // End of generate_moip_form




		//======================================================================
		// Check if the Moip response is a NASP request or simply a checkout return
		//======================================================================
		function check_ipn_request_is_valid() {
			global $woocommerce;

			if ($this->debug=='yes') $this->log->add($this->id, 'Verificando a resposta do MoIP...' );

			if (count($_POST) > 0) {

				// POST recebido, indica que é a resposta do NASP.
				if ($this->debug=='yes') $this->log->add($this->id, 'POST recebido, indica que é a requisição do NASP.');
				if ($this->debug=='yes') $this->log->add($this->id, $_POST['id_transacao'] . ' / '. $_GET['validator']);

				$transacaoID = isset($_POST['id_transacao']) ? $_POST['id_transacao'] : '';
				$validator = isset($_GET['validator']) ? $_GET['validator'] : '';

				//Verifica se o POST realmente foi enviado pelo MoIP
				if ($validator == $this->token_validator){
					if ($this->debug=='yes') $this->log->add($this->id, 'POST Validado!');

					if ($transacaoID != ''){
						if ($this->debug=='yes') $this->log->add($this->id, 'Número da Transação = '. $transacaoID);
						header("HTTP/1.0 200 OK");
						return true;
					} else {
						if ($this->debug=='yes') $this->log->add($this->id, 'Não foi possível encontrar o id da transação no POST');
					}
				} else {
					if ($this->debug=='yes') $this->log->add($this->id, 'Não foi possível validar se o POST foi enviado pelo MoIP');
				}
			} else {
				// POST não recebido, indica que a requisição é o retorno do Checkout PagSeguro.
				// No término do checkout o usuário é redirecionado para este bloco.
				return true;
			}
			header("HTTP/1.0 400 FAILED");
			return false;
		} // End of check_ipn_request_is_valid



		//======================================================================
		// Check for MoIP NASP response
		//======================================================================
		function check_ipn_response() {
			if ( !empty($_POST['id_transacao']) && !empty($_POST['cod_moip']) ) {
				$_POST = stripslashes_deep($_POST);
				if ($this->debug=='yes') $this->log->add($this->id, 'Analisando POST...');
				if ($this->check_ipn_request_is_valid()){
					if ($this->debug=='yes') $this->log->add($this->id, 'POST OK. Atualizando Pedido...');
					do_action("valid-moip-standard-ipn-request", $_POST);
				} else {
					if ($this->debug=='yes') $this->log->add($this->id, 'Validação do POST Falhou.');
				}
			}
		} //End of check_ipn_response



		//======================================================================
		// Successful Payment!
		//======================================================================
		function successful_request( $posted ) {
			$transacao = explode('-', $posted['id_transacao']);
			$posted['id_transacao'] = $transacao[0];

			if ($this->debug=='yes') $this->log->add($this->id, 'Pedido = '.$posted['id_transacao'].' / Status = '.$posted['status_pagamento']);

			if ( !empty($posted['id_transacao']) && !empty($posted['cod_moip']) ) {
				$order = new WC_Order( (int) $posted['id_transacao'] );

				// Check order not already completed
				if ($order->status == 'completed' && (int)$posted['status_pagamento'] == 4) {
					if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$posted['id_transacao'].' já se encontra completado no sistema!');
					exit;
				}

				// We are here so lets check status and do actions
				switch ((int)$posted['status_pagamento']){
					case 1: //autorizado
						// Payment completed
						$order->add_order_note( __('Pagamento já foi realizado porém ainda não foi creditado na Carteira MoIP recebedora (devido ao floating da forma de pagamento).', 'woothemes') );
						$order->payment_complete();
						// Store MoIP Details
						update_post_meta( $order->id, '_f2m_moiptransparente_email', $posted['email_consumidor']);
						update_post_meta( $order->id, '_f2m_moiptransparente_cod_moip', $posted['cod_moip']);
						update_post_meta( $order->id, '_f2m_moiptransparente_tipo_pagto', $posted['tipo_pagamento']);
						update_post_meta( $order->id, '_f2m_moiptransparente_parcelas', $posted['parcelas']);
						update_post_meta( $order->id, '_f2m_moiptransparente_data', date("F j, Y, g:i a"));
						if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$order->id.': Pagamento já foi realizado porém ainda não foi creditado na Carteira MoIP recebedora (devido ao floating da forma de pagamento).');
						break;

						
					case 2: //iniciado
						$order->update_status('processing','Pagamento está sendo realizado ou janela do navegador foi fechada (pagamento abandonado).');
						//$order->add_order_note( __('Pagamento está sendo realizado ou janela do navegador foi fechada (pagamento abandonado).', 'woothemes') );
						if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$order->id.': Pagamento está sendo realizado ou janela do navegador foi fechada (pagamento abandonado).');
						break;

						
					case 3: //boleto impresso
						$order->add_order_note( __('Boleto foi impresso e ainda não foi pago.', 'woothemes') );
						if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$order->id.': Boleto foi impresso e ainda não foi pago.');
						break;

					
					case 4: //'concluido':
						$order->add_order_note( __('Pagamento já foi realizado e dinheiro já foi creditado na Carteira MoIP recebedora.', 'woothemes') );
						if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$order->id.': Pagamento pelo MoIP compensado e transação finalizada.');
						break;

						
					case 5: //cancelado
						$order->update_status('cancelled','Pagamento foi cancelado pelo pagador, instituição de pagamento, MoIP ou recebedor antes de ser concluído.');
						if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$order->id.': Pagamento foi cancelado pelo pagador, instituição de pagamento, MoIP ou recebedor antes de ser concluído.');
						break;

						
					case 6: //'em análise'
						$order->update_status('on-hold','Pagamento foi realizado com cartão de crédito e autorizado, porém está em análise pela Equipe MoIP. Não existe garantia de que será concluído.');
						if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$order->id.': Pagamento foi realizado com cartão de crédito e autorizado, porém está em análise pela Equipe MoIP. Não existe garantia de que será concluído.');
						break;

						
					case 7: //estornado
						$order->update_status('failed','Pagamento foi estornado pelo pagador, recebedor, instituição de pagamento ou MoIP');
						if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$order->id.': Pagamento foi estornado pelo pagador, recebedor, instituição de pagamento ou MoIP.');
						break;

						
					case 8: //em revisao
						$order->add_order_note( __('Pagamento está em revisão pela equipe de Disputa ou por Chargeback (Deprecated).', 'woothemes') );
						if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$order->id.': Pagamento está em revisão pela equipe de Disputa ou por Chargeback (Deprecated).');
						break;
						
						
					case 9: //reembolsado
						$order->update_status('cancelled','Pagamento foi reembolsado diretamente para a carteira MoIP do pagador pelo recebedor do pagamento ou pelo MoIP.');
						if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$order->id.': Pagamento foi reembolsado diretamente para a carteira MoIP do pagador pelo recebedor do pagamento ou pelo MoIP.');
						break;

						
					default:
						// No action
						break;
				}
			}
		} //End of successful_request()



		//---------------------------------------------------------------------------------------------------
		//Função: cielodireto_check_missing_fields_message
		//Descrição: Exibe um mensagem de erro no painel de controle se as configurações mandatórias para a
		//           execução do plugin não estiverem corretamente configuradas.
		//---------------------------------------------------------------------------------------------------
		function cielodireto_check_missing_fields_message(){
			global $woocommerce;

			$message = '';
			if(!is_plugin_active('woocommerce-fields/woocommerce-fields.php')) $message .= '<p> - O plugin WooCommerce Fields não foi encontrado. Para que o checkout transparente funcione corretamente é necessário ter este plugins instalado.</p>';
			if(empty($this->key)) $message .= '<p> - Obrigatório informar a Chave do MoIP API.</p>';
			if(empty($this->token)) $message .= '<p> - Obrigatório informar o Token do MoIP API.</p>';
			if(empty($this->user)) $message .= '<p> - Obrigatório informar o nome do usuário da conta MoIP.</p>';
			if(empty($this->email)) $message .= '<p> - Obrigatório informar o email do usuário da conta MoIP.</p>';

			if(!empty($message)){
				$message = '<div class="error">' .
						   '<strong>Gateway MoIP Transparente Desabilitado!</strong> Verifique os erros abaixo:' .
						   $message .
						   sprintf( __( 'Clique %saqui%s para configurar!' , 'woothemes' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways#gateway-'.$this->id.'">', '</a>' ) .
						   '</div>';
			}
			echo $message;
		} //Fim da função cielodireto_check_missing_fields_message


	} // End of class woocommerce_moip_transparente
}
?>