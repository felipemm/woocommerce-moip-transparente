<?php
/*
Plugin Name: WooCommerce Moip Checkout Transparente
Plugin URI: http://felipematos.com/loja
Description: Adiciona o gateway de pagamento do Moip no WooCommerce (com checkout transparente)
Version: 1.0
Author: Felipe Matos <chucky_ath@yahoo.com.br>
Author URI: http://felipematos.com
Requires at least: 3.4
Tested up to: 3.4.2
*/

//hook to include the payment gateway function
add_action('plugins_loaded', 'gateway_moip_transparente', 0);

//======================================================================
//hook function for MOIP PAYMENT GATEWAY
//======================================================================
function gateway_moip_transparente(){
  require_once "moip-api/lib/Moip.php";
  require_once "moip-api/lib/MoipClient.php";
  require_once "moip-api/lib/MoipStatus.php";

  //======================================================================
  //Moip payment gateway class
  //======================================================================
  class woocommerce_moip_transparente_transparente extends woocommerce_payment_gateway {

    //======================================================================
    // object constructor function
    //======================================================================
    public function __construct() {
      global $woocommerce;

      $this->id           = 'moiptransparente';
      $this->icon         = apply_filters('woocommerce_'.$this->id.'_icon', $url = plugin_dir_url(__FILE__).'moip.png');
      $this->method_title = __('Moip Transparente', 'woothemes');
      $this->has_fields   = false;

      // Load the form fields.
      $this->init_form_fields();

      // Load the settings.
      $this->init_settings();

      // Define user set variables
      $this->title           = $this->settings['title'];
      $this->email           = $this->settings['email'];
      $this->token           = $this->settings['token'];
      $this->key             = $this->settings['key'];
      $this->token_validator = $this->settings['token_validator'];
      $this->store_nickname  = $this->settings['store_nickname'];
      $this->debug           = $this->settings['debug'];
      $this->enable_credito  = $this->settings['enable_credito'];
      $this->enable_debito   = $this->settings['enable_debito'];
      $this->enable_boleto   = $this->settings['enable_boleto'];
      //$this->boleto_logo     = $this->settings['boleto_logo'];
      //$this->boleto_days     = $this->settings['boleto_days'];
      //$this->boleto_line1    = $this->settings['boleto_line1'];
      //$this->boleto_line2    = $this->settings['boleto_line2'];
      //$this->boleto_line3    = $this->settings['boleto_line3'];
      $this->testmode        = $this->settings['testmode'];

      // Logs
      if ($this->debug=='yes') $this->log = $woocommerce->logger();

      // Actions
      add_action('init', array(&$this, 'check_ipn_response'));
      add_action('valid-moip-standard-ipn-request', array(&$this, 'successful_request'));
      add_action('woocommerce_receipt_'.$this->id, array(&$this, 'receipt_page'));
      add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));

      if ( !$this->is_valid_for_use() ) $this->enabled = false;
    } //End of __construct()



    //======================================================================
    //Check if this gateway is enabled and available in the user's country
    //======================================================================
    function is_valid_for_use() {
      if (!in_array(get_option('woocommerce_currency'), array('BRL')))
        return false;
      return true;
    } // End is_valid_for_use()



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
        /*'boleto_logo' => array(
          'title' => __( 'Logo boleto', 'woothemes' ),
          'type' => 'text',
          'description' => __( 'URL do logo que irá aparecer no boleto.', 'woothemes' ),
          'default' => ''
        ),
        'boleto_days' => array(
          'title' => __( 'Numero de dias vencimento', 'woothemes' ),
          'type' => 'text',
          'description' => __( 'Quantos dias após data de geração do boleto ele deve vencer.', 'woothemes' ),
          'default' => '5'
        ),
        'boleto_line1' => array(
          'title' => __( 'Instrução Boleto Linha 1', 'woothemes' ),
          'type' => 'text',
          'description' => __( 'Instruções a serem incluídas no boleto para o banco.', 'woothemes' )
        ),
        'boleto_line2' => array(
          'title' => __( 'Instrução Boleto Linha 2', 'woothemes' ),
          'type' => 'text',
          'description' => __( 'Instruções a serem incluídas no boleto para o banco.', 'woothemes' )
        ),
        'boleto_line3' => array(
          'title' => __( 'Instrução Boleto Linha 3', 'woothemes' ),
          'type' => 'text',
          'description' => __( 'Instruções a serem incluídas no boleto para o banco.', 'woothemes' )
        ),*/
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



    //======================================================================
    //generate the form to send to moip
    //======================================================================
    function generate_moip_form($order_id){
      global $woocommerce;
      $order = &new woocommerce_order( $order_id );
      $moip = new Moip();
      
      //set the environment
      $moip->setEnvironment($this->testmode == 'yes' ? 'test' : 'prod');
      //set credentials and receiver information
      $moip->setCredential(array('key' => $this->key,'token' => $this->token));
      $moip->setReceiver($this->email);
      $moip->setNotificationURL('<![CDATA['.htmlspecialchars($this->get_return_url($order)).']]>');

      //set order information
      $moip->setUniqueID($order->id);
      $moip->setValue(number_format($order->get_total(),2,".",""));
      $moip->setReason('Compra feita: '. $this->store_nickname);
      //put product description in the payment
      foreach ($order->get_items() as $item){
        $moip->addMessage($item['name'] . ' - ' . $item['qty'] . ' Un.');
      }

      //set payment methods that will be available for the customer
      $moip->addPaymentWay('creditCard');
      $moip->addPaymentWay('billet');
      $moip->addPaymentWay('financing');
      $moip->addPaymentWay('debit');
      $moip->addPaymentWay('debitCard');
      //set boleto bancario information
      //$moip->setBilletConf(strtotime(date("Y-m-d", strtotime($date)) . " +".$this->boleto_days." day"), true, array($this->boleto_line1, $this->boleto_line2, $this->boleto_line3), $this->boleto_logo);

      //set customer information
      $endereco = explode(',',$order->billing_address_1);
      $endereco[1] = str_replace('º','',str_replace('N','',strtoupper(str_replace('-','',$endereco[1]))));
      $complemento = explode(',',$order->billing_address_2);
      
      $moip->setPayer(
        array(
          'name' => $order->billing_first_name . " " . $order->billing_last_name,
          'email' => $order->billing_email,
          'payerId' => $order->billing_email,
          'billingAddress' => array(
            'address' => $endereco[0],
            'number' => trim($endereco[1]),
            'complement' => $complemento[0],
            'neighborhood' => utf8_decode (trim($complemento[1])),
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
        

        //add the javascript and html code necessary to do the checkout on the client side
        $payment_form = "
          <script type='text/javascript'><!--

            //valida numero inteiro com mascara
            function mascaraInteiro(el, event){
              if (event.keyCode < 48 || event.keyCode > 57){
                event.returnValue = false;
                return false;
              }
              return true;
            }

            //formata de forma generica os campos
            function formataCampo(campo, Mascara, evento) {
              var boleanoMascara;

              var Digitato = evento.keyCode;
              exp = /\-|\.|\/|\(|\)| /g
              campoSoNumeros = campo.value.toString().replace( exp, '' );

              var posicaoCampo = 0;
              var NovoValorCampo='';
              var TamanhoMascara = campoSoNumeros.length;;

              if (Digitato != 8) { // backspace
                for(i=0; i<= TamanhoMascara; i++) {
                  boleanoMascara  = ((Mascara.charAt(i) == '-') || (Mascara.charAt(i) == '.') || (Mascara.charAt(i) == '/'))
                  boleanoMascara  = boleanoMascara || ((Mascara.charAt(i) == '(') || (Mascara.charAt(i) == ')') || (Mascara.charAt(i) == ' '))
                  if (boleanoMascara) {
                    NovoValorCampo += Mascara.charAt(i);
                    TamanhoMascara++;
                  }else {
                    NovoValorCampo += campoSoNumeros.charAt(posicaoCampo);
                    posicaoCampo++;
                  }
                }
                campo.value = NovoValorCampo;
                return true;
              }else {
                return true;
              }
            }

            //adiciona mascara ao numero do cartão de crédito
            function MascaraCartao(cartao, event){
              if(mascaraInteiro(cartao,event)==false){
                event.returnValue = false;
              }
              var mascara = '';
              switch(document.getElementById('tipoCartao').value){
                case 'AmericanExpress':
                  mascara = '000000000000000';
                  break;
                case 'Diners':
                  mascara = '00000000000000';
                  break;
                case 'Mastercard':
                  mascara = '0000000000000000';
                  break;
                case 'Hipercard':
                  mascara = '000000000000000000';
                  break;
                case 'Visa':
                  mascara = '0000000000000000';
                  break;
              }
              return formataCampo(cartao, mascara, event);
            }

            //adiciona mascara ao CPF
            function MascaraCPF(cpf,event){
              if(mascaraInteiro(cpf,event)==false){
                event.returnValue = false;
              }
              return formataCampo(cpf, '000.000.000-00', event);
            }

            //valida o CPF digitado
            function ValidarCPF(Objcpf){
              var cpf = Objcpf.value;
              exp = /\.|\-/g
              cpf = cpf.toString().replace( exp, '' );
              var digitoDigitado = eval(cpf.charAt(9)+cpf.charAt(10));
              var soma1=0, soma2=0;
              var vlr =11;

              for(i=0;i<9;i++){
                soma1+=eval(cpf.charAt(i)*(vlr-1));
                soma2+=eval(cpf.charAt(i)*vlr);
                vlr--;
              }
              soma1 = (((soma1*10)%11)==10 ? 0:((soma1*10)%11));
              soma2=(((soma2+(2*soma1))*10)%11);

              var digitoGerado=(soma1*10)+soma2;
              if(digitoGerado!=digitoDigitado){
                alert('CPF Invalido!');
                Objcpf.select();
              }
            }

            //valida data
            function ValidaVencimento(data){
              exp = /\d{2}\/\d{4}/
              if(!exp.test(data.value)){
                alert('Data Invalida!');
                data.focus();
                data.select();
              }
            }

            //adiciona mascara de data
            function MascaraVencimento(data,event){
              if(mascaraInteiro(data,event)==false){
                event.returnValue = false;
              }
              return formataCampo(data, '00/0000', event);
            }

            //valida data
            function ValidaData(data){
              exp = /\d{2}\/\d{2}\/\d{4}/
              if(!exp.test(data.value)){
                alert('Data Invalida!');
                data.focus();
                data.select();
              }
            }

            //adiciona mascara de data
            function MascaraData(data,event){
              if(mascaraInteiro(data,event)==false){
                event.returnValue = false;
              }
              return formataCampo(data, '00/00/0000', event);
            }

            function getCheckedRadioId(name) {
              var elements = document.getElementsByName(name);

              for (var i=0, len=elements.length; i<len; ++i)
                if (elements[i].checked) return elements[i].value;
            }

            //====================================================================================================


            //used to store the payment information
            var settings;

            //change the select credit card holder
            function changeCreditCardType(tipo){
              var cartao = document.getElementById('numeroCartao');

              //set the length of the card number based on its type
              switch(tipo.value){
                case 'AmericanExpress':
                  document.getElementById('numeroCartao').maxLength = 15;
                  document.getElementById('codigoSeguranca').maxLength = 4;
                  break;
                case 'Diners':
                  document.getElementById('numeroCartao').maxLength = 14;
                  document.getElementById('codigoSeguranca').maxLength = 3;
                  break;
                case 'Mastercard':
                  document.getElementById('numeroCartao').maxLength = 16;
                  document.getElementById('codigoSeguranca').maxLength = 3;
                  break;
                case 'Hipercard':
                  document.getElementById('numeroCartao').maxLength = 16;
                  document.getElementById('codigoSeguranca').maxLength = 3;
                  break;
                case 'Visa':
                  document.getElementById('numeroCartao').maxLength = 16;
                  document.getElementById('codigoSeguranca').maxLength = 3;
                  break;
              }

              //clear current value and set focus
              cartao.value = '';
              cartao.select();
            }

            //function to execute the payment selected by the customer
            function executePayment() {
              document.getElementById('paymentWaiting').innerHTML = '<img src=\"".esc_url( $woocommerce->plugin_url() )."/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\"/>';
              document.getElementById('paymentWaiting').innerHTML += 'Processando o pagamento...';
              
          
              changePaymentType(getCheckedRadioId('paymentType'));
                  document.getElementById('paymentWaiting').style.display = 'block';
             
              document.getElementById('paymentOptions').style.display = 'none';
               document.getElementById('paymentButtons').style.display = 'none';
              setTimeout(function(){
                  MoipWidget(settings);
              },2000);
            }

            function cancelPayment(){
              window.location = \"".esc_url( $order->get_cancel_order_url() )."\";
            }

            //calback function when the request was made sucessfully
            var funcaoSucesso = function(data){
              var btnPagarPedido    = document.getElementById('paymentButton');
              var btnCancelarPedido = document.getElementById('cancelButton');
              var btnConfirmaPedido = document.getElementById('confirmOrder');
              var btnImprimeBoleto  = document.getElementById('printBoleto');
              var divOpcao          = document.getElementById('paymentOptions');
              var divAguardando     = document.getElementById('paymentWaiting');
              var divResultado      = document.getElementById('paymentResult');
              var tipoPagamento     = getCheckedRadioId('paymentType');


              //if the request was successful, do the stuff to show it and confirm the order
              if(data.StatusPagamento == 'Sucesso'){
                  document.getElementById('paymentButton').style.display = 'none';
                  document.getElementById('cancelButton').style.display = 'none';
                   document.getElementById('confirmOrder').style.display = 'inline'
                  document.getElementById('paymentButtons').style.display = 'block';
                   document.getElementById('paymentResult').style.display = 'inline';
                  document.getElementById('paymentWaiting').style.display = 'none';

                document.getElementById('paymentResult').innerHTML = '<img src=\"".esc_url( $woocommerce->plugin_url() )."/assets/images/success.png\" alt=\"Completo!\" style=\"float:left; margin-right: 10px;\"/> Pagamento concluído!';
                document.getElementById('paymentResult').innerHTML += '<br><br>Sua transação foi processada pelo Moip Pagamentos S/A.';
                document.getElementById('paymentResult').innerHTML += '<br><br>Caso tenha alguma dúvida referente a transação, entre em contato com o Moip.';
                
                
                  btnConfirmaPedido.style.display = 'inline';
                  btnConfirmaPedido.onclick = function(){
                    window.location = '".$this->get_return_url($order)."';
                  }

                //alert(tipoPagamento);
                if(tipoPagamento == 'CartaoCredito'){
                  document.getElementById('paymentResult').innerHTML += 'A sua transação está '+data.Status+' e o código Moip é ' + data.CodigoMoIP;

                  setTimeout(function(){
                    window.location = '".$this->get_return_url($order)."';
                  },7000);

                } else {
                  var win = window.open(data.url);

                    btnImprimeBoleto.style.display = 'inline';
                    btnImprimeBoleto.onclick = function(){
                      window.open(data.url);
                    }
                  //if boleto, show the print boleto button
                  if(tipoPagamento == 'BoletoBancario'){
                         btnImprimeBoleto.innerHTML = 'Imprimir Boleto';
                   } else {
                         btnImprimeBoleto.innerHTML = 'Acessar banco';
                   }
                }
              } else {
                //if the request has an error, show to the customer
                divResultado.innerHTML = '<img src=\"".esc_url( $woocommerce->plugin_url() )."/assets/images/error.gif\" alt\"Completo\" style=\"float:left; margin-right: 10px;\"/> Houve um erro ao processar o seu pagamento!';
                divResultado.innerHTML += '<br><br>O MoIP retornou a seguinte mensagem: ' + data.Mensagem;

                //set div visibility accordingly
                divOpcao.style.display          = 'inline';
                divAguardando.style.display     = 'none';
                divResultado.style.display      = 'none';

                btnPagarPedido.style.display    = 'inline';
                btnCancelarPedido.style.display = 'inline';
                btnConfirmaPedido.style.display = 'none';
                btnImprimeBoleto.style.display  = 'none';
              }
            };

            //callback function when the request has an error
            var funcaoFalha = function(data) {
              var btnPagarPedido    = document.getElementById('paymentButton');
              var btnCancelarPedido = document.getElementById('cancelButton');
              var btnConfirmaPedido = document.getElementById('confirmOrder');
              var btnImprimeBoleto  = document.getElementById('printBoleto');
              var divOpcao          = document.getElementById('paymentOptions');
              var divAguardando     = document.getElementById('paymentWaiting');
              var divResultado      = document.getElementById('paymentResult');

              //set div visibility accordingly
              divOpcao.style.display          = 'inline';
              divAguardando.style.display     = 'none';
              divResultado.style.display      = 'block';

              btnPagarPedido.style.display    = 'inline';
              btnCancelarPedido.style.display = 'inline';
              btnConfirmaPedido.style.display = 'none';
              btnImprimeBoleto.style.display  = 'none';

               document.getElementById('paymentButtons').style.display = 'block';
              divResultado.innerHTML = '<img src=\"".esc_url( $woocommerce->plugin_url() )."/assets/images/error.gif\" alt=\"Completo!\" style=\"float:left; margin-right: 10px;\"/> Houve um erro ao processar o seu pagamento!';
              divResultado.innerHTML += '<br><br>O MoIP retornou a seguinte mensagem: ".utf8_encode(' + JSON.stringify(data)')."' ;
            };


            function changePaymentType(id){
              var formCartaoCredito = document.getElementById('paymentFormCredito');
              var formCartaoDebito  = document.getElementById('paymentFormDebito');
              var tipoBancoDebito   = document.getElementById('tipoBancoDebito');
              document.getElementById('paymentButtons').style.display = 'block';
              

              switch(id){
                case 'CartaoCredito':
                  formCartaoCredito.style.display = 'inline';
                  formCartaoDebito.style.display  = 'none';

                  var instituicao     = document.getElementById('tipoCartao').value;
                  var numeroCartao    = document.getElementById('numeroCartao').value;
                  var dataValidade    = document.getElementById('dataValidade').value;
                  var codigoSeguranca = document.getElementById('codigoSeguranca').value;
                  var nomePortador    = document.getElementById('nomePortador').value;
                  var dataNascimento  = document.getElementById('dataNascimento').value;
                  var telefone        = document.getElementById('Telefone').value;
                  var cpf             = document.getElementById('cpf').value

                  settings = {
                    'Forma': 'CartaoCredito',
                    'Instituicao': instituicao,
                    'Parcelas': '1',
                    'Recebimento': 'AVista',
                    'CartaoCredito': {
                      'Numero': numeroCartao,
                      'Expiracao': dataValidade,
                      'CodigoSeguranca': codigoSeguranca,
                      'Portador': {
                        'Nome': nomePortador,
                        'DataNascimento': dataNascimento,
                        'Telefone': telefone,
                        'Identidade': cpf
                      }
                    }
                  }
                  break;
                case 'DebitoBancario':
                  formCartaoCredito.style.display = 'none';
                  formCartaoDebito.style.display  = 'inline';

                  settings = {
                    'Forma': 'DebitoBancario',
                    'Instituicao':tipoBancoDebito.value
                  }
                  break;
                default:
                  formCartaoCredito.style.display = 'none';
                  formCartaoDebito.style.display  = 'none';

                  settings = {
                    'Forma': 'BoletoBancario',
                  }
                  break;
              }
              //alert(JSON.stringify(settings));
          
            }
          --></script>

          <div id='MoipWidget'
            data-token='".$resposta->token."'
            callback-method-success='funcaoSucesso'
            callback-method-error='funcaoFalha'>
          </div>

          <div id='payment_form'>

            <div id='paymentResult'></div>

            <div id='paymentWaiting'></div>

            <div id='paymentOptions'>
        ";
        
		
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
                      <td><input type='text' id='dataValidade' onKeyPress='MascaraVencimento(this, event);' maxlength='7' onBlur= 'ValidaVencimento(this);' /></td>
                    </tr>
                    <tr>
                      <td>Data de Nascimento </td>
                      <td><input type='text' id='dataNascimento' onKeyPress='MascaraData(this, event);' maxlength='10' onBlur= 'ValidaData(this);' /></td>
                    </tr>
                    <tr>
                      <td>Telefone</td>
                      <td><input type='text' id='Telefone' value='".$order->billing_phone."' /> (99)9999-9999</td>
                    </tr>
                    <tr>
                      <td>CPF</td>
                      <td><input type='text' id='cpf' onBlur='ValidarCPF(this);' onKeyPress='MascaraCPF(this, event);' maxlength='14' /></td>
                    </tr>

                  </table>
                </div>
          ";
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
                </div>
          ";
        } else {
          $payment_form .= "<div id='paymentFormDebito'></div>";
        }
        
        if($this->enable_boleto == 'yes'){
          $payment_form .= "
                <input type='radio' name='paymentType' id='paymentType' value='BoletoBancario' onclick='changePaymentType(this.value)' /> Boleto Bancário <br><br>
          ";
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
          <script type='text/javascript' src='".($this->testmode == 'yes' ? 'https://desenvolvedor.moip.com.br/sandbox' : 'https://www.moip.com.br')."/transparente/MoipWidget-v2.js' charset='utf-8'></script>
        ";
      } else {
        //houve um erro na transação, lançar no log
        if ($this->debug=='yes') $this->log->add($this->id, 'Houve um erro ao processar o XML: '. $resposta->error);
        $payment_form = utf8_encode($resposta->error);
        $payment_form .= "<br><button id='cancelButton'  class='button' onclick='window.location = \"".esc_url( $order->get_cancel_order_url() )."\";'  style='display: inline;'>Cancelar Pedido</button>";
      }
        
      if ($this->debug=='yes') $this->log->add($this->id, "Pedido gerado com sucesso. Abaixo código HTML do formulário:");
      if ($this->debug=='yes') $this->log->add($this->id, $payment_form);

      return $payment_form;
    } // End of generate_moip_form()



    //======================================================================
    // Process the payment and return the result
    //======================================================================
    function process_payment( $order_id ) {
      $order = &new woocommerce_order( $order_id );

      return array(
          'result'   => 'success',
          'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
      );
    } //End of process_payment()



    //======================================================================
    // create the receipt page. This is the page after the order is placed.
    //======================================================================
    function receipt_page( $order ) {
      echo '<p>'.__('Obrigado pelo seu pedido. Por favor clique em Pagar com MoIP para finalizar a transação.', 'woothemes').'</p>';
      echo '<p>'.__('* A sua transação será processada diretamente através da Moip Pagamentos S/A. Nenhuma informação sensível é compartilhada com o site.', 'woothemes').'</p>';

      //Check if we already have the token for the moip payment form for this order. If we do,
      // the generate_moip_form() will not re-submit the request, it will simply use the token
      // to redirect the user to the payment form, otherwise it will create the form and then
      // store the generated token for future use in case the client doesn´t pay right away.
      $moip_payment_token = get_post_meta($order->id, 'moip_payment_token', true);
      echo $this->generate_moip_form( $order ) . $moip_payment_token;
      update_post_meta($order->id, 'moip_payment_token', $moip_payment_token);
    } // End of receipt_page()



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
    } // End of check_ipn_request_is_valid()



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
    } //End of check_ipn_response()



    //======================================================================
    // Successful Payment!
    //======================================================================
    function successful_request( $posted ) {
      if ($this->debug=='yes') $this->log->add($this->id, 'Pedido = '.$posted['id_transacao'].' / Status = '.$posted['status_pagamento']);

      if ( !empty($posted['id_transacao']) && !empty($posted['cod_moip']) ) {
        $order = new woocommerce_order( (int) $posted['id_transacao'] );

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
            update_post_meta( (int) $posted['id_transacao'], 'E-Mail MoIP', $posted['email_consumidor']);
            update_post_meta( (int) $posted['id_transacao'], 'Código Transação', $posted['cod_moip']);
            update_post_meta( (int) $posted['id_transacao'], 'Método Pagamento', $posted['tipo_pagamento']);
            update_post_meta( (int) $posted['id_transacao'], 'Data Transação', date("F j, Y, g:i a"));
            if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$posted['id_transacao'].': Pagamento já foi realizado porém ainda não foi creditado na Carteira MoIP recebedora (devido ao floating da forma de pagamento).');
            break;

          case 2: //iniciado

            $order->add_order_note( __('Pagamento está sendo realizado ou janela do navegador foi fechada (pagamento abandonado).', 'woothemes') );
            if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$posted['id_transacao'].': Pagamento está sendo realizado ou janela do navegador foi fechada (pagamento abandonado).');
            break;

          case 3: //boleto impresso

            $order->add_order_note( __('Boleto foi impresso e ainda não foi pago.', 'woothemes') );
            if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$posted['id_transacao'].': Boleto foi impresso e ainda não foi pago.');
            break;

          case 4: //'concluido':

            $order->add_order_note( __('Pagamento já foi realizado e dinheiro já foi creditado na Carteira MoIP recebedora.', 'woothemes') );
            if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$posted['id_transacao'].': Pagamento pelo MoIP compensado e transação finalizada.');
            break;

          case 5: //cancelado

            $order->update_status('cancelled','Pagamento foi cancelado pelo pagador, instituição de pagamento, MoIP ou recebedor antes de ser concluído.');
            if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$posted['id_transacao'].': Pagamento foi cancelado pelo pagador, instituição de pagamento, MoIP ou recebedor antes de ser concluído.');
            break;

          case 6: //'em análise'

            $order->update_status('on-hold','Pagamento foi realizado com cartão de crédito e autorizado, porém está em análise pela Equipe MoIP. Não existe garantia de que será concluído.');
            if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$posted['id_transacao'].': Pagamento foi realizado com cartão de crédito e autorizado, porém está em análise pela Equipe MoIP. Não existe garantia de que será concluído.');
            break;

          case 7: //estornado

            $order->update_status('failed','Pagamento foi estornado pelo pagador, recebedor, instituição de pagamento ou MoIP');
            if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$posted['id_transacao'].': Pagamento foi estornado pelo pagador, recebedor, instituição de pagamento ou MoIP.');
            break;

          case 8: //em revisao

            $order->add_order_note( __('Pagamento está em revisão pela equipe de Disputa ou por Chargeback (Deprecated).', 'woothemes') );
            if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$posted['id_transacao'].': Pagamento está em revisão pela equipe de Disputa ou por Chargeback (Deprecated).');
            break;

          case 9: //reembolsado

            $order->update_status('cancelled','Pagamento foi reembolsado diretamente para a carteira MoIP do pagador pelo recebedor do pagamento ou pelo MoIP.');
            if ($this->debug=='yes') $this->log->add($this->id, 'Pedido '.$posted['id_transacao'].': Pagamento foi reembolsado diretamente para a carteira MoIP do pagador pelo recebedor do pagamento ou pelo MoIP.');
            break;

          default:
            // No action
            break;
        }
      }
    } //End of successful_request()
  } // End of class woocommerce_moip_transparente



  //======================================================================
  //Add the gateway to WooCommerce
  //======================================================================
  function add_moip_gateway( $methods ) {
    $methods[] = 'woocommerce_moip_transparente_transparente'; return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'add_moip_gateway' );
}
