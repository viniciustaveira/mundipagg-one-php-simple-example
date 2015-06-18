<?php

try
{
    // Validação muito simples dos dados recebidos
    // OBS.: NÃO DEIXE ASSIM EM PRODUÇÃO
    if ($_SERVER['REQUEST_METHOD'] != "POST" || empty($_POST['number']) || empty($_POST['name']) || empty($_POST['expiry']) || empty($_POST['cvc'])) {
        header('Content-Type: application/json');
        http_response_code(400);
        print json_encode(array("message" => "Campos obrigatórios inválidos."));
        exit;
    }

    // Separa mes e ano da data de validade do cartão
    $expiryParts = explode('/', $_POST['expiry']);
    $expMonth = @$expiryParts[0];
    $expYear = @$expiryParts[1];

    // Carrega dependências
    require_once(dirname(__FILE__) . '/vendor/autoload.php');

    // Define o ambiente utilizado (produção ou homologação)
    \MundiPagg\ApiClient::setEnvironment(\MundiPagg\One\DataContract\Enum\ApiEnvironmentEnum::INSPECTOR);

    // Define a chave da loja
    \MundiPagg\ApiClient::setMerchantKey("be43cb17-3637-44d0-a45e-d68aaee29f47");

    // Cria objeto requisição
    $createSaleRequest = new \MundiPagg\One\DataContract\Request\CreateSaleRequest();

    /**
     * Regras do simulador:
     * R$ 1.000,00 = Autorizada
     * R$ 1.050,01 = Timeout
     * R$ 1.500,00 = Não autorizada
     */

    // Define dados do pedido
    $createSaleRequest->addCreditCardTransaction()
        ->setPaymentMethodCode(\MundiPagg\One\DataContract\Enum\PaymentMethodEnum::SIMULATOR)
        ->setAmountInCents(1000)
        ->getCreditCard()
            ->setCreditCardBrand(\MundiPagg\One\DataContract\Enum\CreditCardBrandEnum::MASTERCARD)
            ->setCreditCardNumber($_POST['number'])
            ->setExpMonth($expMonth)
            ->setExpYear($expYear)
            ->setHolderName($_POST['name'])
            ->setSecurityCode($_POST['cvc'])
        ;

    // Cria um objeto ApiClient
    $apiClient = new MundiPagg\ApiClient();

    // Faz a chamada para criação
    $createSaleResponse = $apiClient->createSale($createSaleRequest);

    // Mapeia resposta
    $httpStatusCode = $createSaleResponse->CreditCardTransactionResultCollection[0]->Success ? 201 : 401;
    $response = array("message" => $createSaleResponse->CreditCardTransactionResultCollection[0]->AcquirerMessage);
}
catch (\MundiPagg\One\DataContract\Report\ApiError $error)
{
    $httpStatusCode = @$error->errorCollection->ErrorItemCollection[0]->ErrorCode;
    $response = array("message" => @$error->errorCollection->ErrorItemCollection[0]->Description);
}
catch (\Exception $ex)
{
    $httpStatusCode = 500;
    $response = array("message" => "Ocorreu um erro inesperado.");
}
finally
{
    // Devolve resposta
    header('Content-Type: application/json');
    http_response_code($httpStatusCode);
    print json_encode($response);
}