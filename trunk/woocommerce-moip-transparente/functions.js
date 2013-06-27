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
	  document.getElementById('numeroCartao').maxLength = 19;
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
  document.getElementById('paymentWaiting').innerHTML = '<img src="'+plugin_url+'/assets/images/ajax-loader.gif" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\"/>';
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
  window.location = cancel_order_url;
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

	document.getElementById('paymentResult').innerHTML = '<img src="'+plugin_url+'/assets/images/success.png" alt=\"Completo!\" style=\"float:left; margin-right: 10px;\"/> Pagamento concluído!';
	document.getElementById('paymentResult').innerHTML += '<br><br>Sua transação foi processada pelo Moip Pagamentos S/A.';
	document.getElementById('paymentResult').innerHTML += '<br><br>Caso tenha alguma dúvida referente a transação, entre em contato com o Moip.';


	  btnConfirmaPedido.style.display = 'inline';
	  btnConfirmaPedido.onclick = function(){
		window.location = return_url;
	  }

	//alert(tipoPagamento);
	if(tipoPagamento == 'CartaoCredito'){
	  var status = '';
	  switch(data.Status){
		case 'EmAnalise'     : status = 'Em Análise'; break;
		case 'BoletoImpresso': status = 'Boleto Impresso'; break;
		default              : status = data.Status; break;
	  }
	  document.getElementById('paymentResult').innerHTML += ' A sua transação está \''+status+'\' e o código Moip é ' + data.CodigoMoIP;

	  //setTimeout(function(){
	  //  window.location = '".$this->get_return_url($order)."';
	  //},7000);

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
	divResultado.innerHTML = '<img src="'+plugin_url+'/assets/images/error.gif" alt\"Completo\" style=\"float:left; margin-right: 10px;\"/> Houve um erro ao processar o seu pagamento!';
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
  divResultado.innerHTML = '<img src="'+plugin_url+'/assets/images/error.gif" alt=\"Completo!\" style=\"float:left; margin-right: 10px;\"/> Houve um erro ao processar o seu pagamento!';
  divResultado.innerHTML += '<br><br>O MoIP retornou a seguinte mensagem: ' + JSON.stringify(data);
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
	  var cpf             = document.getElementById('cpf').value;
	  var parcelas        = document.getElementById('numParcelas').value;

	  settings = {
		'Forma': 'CartaoCredito',
		'Instituicao': instituicao,
		'Parcelas': parcelas,
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























function mascara(o,f){
	v_obj=o
	v_fun=f
	setTimeout('execmascara()',1)
}
function execmascara(){
	v_obj.value=v_fun(v_obj.value)
}
function mtel(v){
	//Remove tudo o que não é dígito
	v=v.replace(/\D/g,'');             			
	//Coloca parênteses em volta dos dois primeiros dígitos
	v=v.replace(/^(\d{2})(\d)/g,'($1)$2'); 
	//Coloca hífen entre o quarto e o quinto dígitos
	v=v.replace(/(\d)(\d{4})$/,'$1-$2');    
	return v;
}
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
	var TamanhoMascara = campoSoNumeros.length;
	if (Digitato != 8) { // backspace
		for(i=0; i<= TamanhoMascara; i++) {
			boleanoMascara  = ((Mascara.charAt(i) == '-') || (Mascara.charAt(i) == '.') || (Mascara.charAt(i) == '/'))
			boleanoMascara  = boleanoMascara || ((Mascara.charAt(i) == '(') || (Mascara.charAt(i) == ')') || (Mascara.charAt(i) == ' '))
			if (boleanoMascara) {
				NovoValorCampo += Mascara.charAt(i);
				TamanhoMascara++;
			} else {
				NovoValorCampo += campoSoNumeros.charAt(posicaoCampo);
				posicaoCampo++;
			}
		}
		campo.value = NovoValorCampo;
		return true;
	} else {
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
			mascara = '0000000000000000000';
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
function MascaraTelefone(tel,event){
  if(mascaraInteiro(tel,event)==false){
	event.returnValue = false;
  }
  if(tel.trim().length > 13)
	return formataCampo(tel, '(00)00000-0000', event); //9 digitos
  return formataCampo(tel, '(00)0000-0000', event);
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
	//alert('CPF Invalido!');
	Objcpf.select();
  }
}

//valida data
function ValidaVencimento(data){
	exp = /\d{2}\/\d{4}/
	if(!exp.test(data.value)){
		data.focus();
		data.select();
	}
}


/***
	function to add a month/year mask into the field passed by parameter
***/
function MascaraVencimento(data,event){
	if(mascaraInteiro(data,event)==false){
		event.returnValue = false;
	}
	return formataCampo(data, '00/0000', event);
}


/***
	function to validate if a date field value is a valid date
***/
function ValidaData(data){
	exp = /\d{2}\/\d{2}\/\d{4}/
	if(!exp.test(data.value)){
		data.focus();
		data.select();
	}
}


/***
	function will add a date mask into the field value passed by parameter
***/
function MascaraData(data,event){
	if(mascaraInteiro(data,event)==false){
		event.returnValue = false;
	}
	return formataCampo(data, '00/00/0000', event);
}


/***
	function to return the checked radio button value
***/
function getCheckedRadioId(name) {
  var elements = document.getElementsByName(name);

  for (var i=0, len=elements.length; i<len; ++i)
	if (elements[i].checked) 
		return elements[i].value;
}