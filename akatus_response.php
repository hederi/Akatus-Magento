﻿<?php

class StatusTransacaoAkatus
{
    const AGUARDANDO_PAGAMENTO = 'Aguardando Pagamento';
    const EM_ANALISE = 'Em Análise';
    const APROVADO = 'Aprovado';
    const CANCELADO = 'Cancelado';
    const DEVOLVIDO = 'Devolvido';
    const COMPLETO = 'Completo';
    const ESTORNADO = 'Estornado';
}

#instancia o Mage
require_once 'app/Mage.php';
require_once 'app/code/core/Mage/Sales/Model/Order.php';

$codigoTransacao    = $_POST["transacao_id"];
$statusRecebido     = $_POST["status"];
$tokenRecebido      = $_POST["token"];

$order = getOrder($codigoTransacao);
$tokenNIP = Mage::getStoreConfig('payment/akatus/tokennip', $order->getStoreId());

if($tokenNIP == $tokenRecebido) {
    $novoStatus = getNovoStatus($statusRecebido, $order->getStatus());

    if ($novoStatus) {

        if($statusRecebido == StatusTransacaoAkatus::APROVADO && $order->getTotalPaid() == 0) {
            $invoice = $order->prepareInvoice();
            $invoice->register()->capture();            
            Mage::getModel('core/resource_transaction')
                ->addObject($order)
                ->addObject($invoice->getOrder())
                ->save();

        }

        $orderToUpdateStatus = Mage::getModel('sales/order')->load($order->getId());   
        $orderToUpdateStatus->setStatus($novoStatus);
        Mage::getModel('core/resource_transaction')->addObject($orderToUpdateStatus)->save();
    }
}

function getOrder($codigoTransacao)
{
    $mageRunCode = isset($_SERVER ['MAGE_RUN_CODE'] ) ? $_SERVER ['MAGE_RUN_CODE'] : '';
    $mageRunType = isset($_SERVER ['MAGE_RUN_TYPE'] ) ? $_SERVER ['MAGE_RUN_TYPE'] : 'store';
    Mage::app($mageRunCode, $mageRunType);

    $db = Mage::getSingleton('core/resource')->getConnection('core_write');        

    $retorno = $db->query("SELECT idpedido FROM akatus_transacoes WHERE codtransacao = '".$codigoTransacao."' ORDER BY id DESC");
    $transacao = $retorno->fetch();        

    if ($order = Mage::getModel('sales/order')->load($transacao['idpedido'])) {
        return $order;
    } else {
        return Mage::getModel('sales/order')->loadByIncrementId($transacao['idpedido']);
    }
}

function getNovoStatus($statusRecebido, $statusAtual)
{
    switch ($statusRecebido) {

        case StatusTransacaoAkatus::COMPLETO:
            $listaStatus = array(
                Mage_Sales_Model_Order::STATE_NEW,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                Mage_Sales_Model_Order::STATE_HOLDED
            );                

            if (in_array($statusAtual, $listaStatus)) {
                return Mage_Sales_Model_Order::STATE_COMPLETE;
            } else {
                return false;
            }            

        case StatusTransacaoAkatus::APROVADO:
            $listaStatus = array(
                Mage_Sales_Model_Order::STATE_NEW,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                Mage_Sales_Model_Order::STATE_HOLDED
            );

            if (in_array($statusAtual, $listaStatus)) {
                return Mage_Sales_Model_Order::STATE_COMPLETE;
            } else {
                return false;
            }                

        case StatusTransacaoAkatus::CANCELADO:
            $listaStatus = array(
                Mage_Sales_Model_Order::STATE_NEW,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                Mage_Sales_Model_Order::STATE_HOLDED,
                Mage_Sales_Model_Order::STATE_COMPLETE
            );                

            if (in_array($statusAtual, $listaStatus)) {
                return Mage_Sales_Model_Order::STATE_CANCELED;                    
            } else {
                return false;
            }                

        case StatusTransacaoAkatus::DEVOLVIDO:
            $listaStatus = array(
                Mage_Sales_Model_Order::STATE_NEW,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                Mage_Sales_Model_Order::STATE_HOLDED,
                Mage_Sales_Model_Order::STATE_COMPLETE                
            );                

            if (in_array($statusAtual, $listaStatus)) {
                return Mage_Sales_Model_Order::STATE_CANCELED;                    
            } else {
                return false;
            }

	 case StatusTransacaoAkatus::ESTORNADO:
            $listaStatus = array(
                Mage_Sales_Model_Order::STATE_COMPLETE
            );

            if (in_array($statusAtual, $listaStatus)) {
                return Mage_Sales_Model_Order::STATE_CANCELED;
            } else {
                return false;
            }

        case StatusTransacaoAkatus::AGUARDANDO_PAGAMENTO:
            $listaStatus = array(
                Mage_Sales_Model_Order::STATE_NEW,
                Mage_Sales_Model_Order::STATE_PROCESSING
            );                

            if (in_array($statusAtual, $listaStatus)) {
                return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
            } else {
                return false;
            }

        case StatusTransacaoAkatus::EM_ANALISE:
            $listaStatus = array(
                Mage_Sales_Model_Order::STATE_NEW,
                Mage_Sales_Model_Order::STATE_PROCESSING
            );                

            if (in_array($statusAtual, $listaStatus)) {
                return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
            } else {
                return false;
            }                                                

        default:
            return Mage_Sales_Model_Order::STATE_PROCESSING;                    
    } 
}
