<?php

namespace App\Controller;

use App\Controller\AppController;
use Cake\Core\Configure;
use Cake\Database\Exception;

/**
 * VoucherTransactions Controller
 * @property \App\Model\Table\UsersTable $Users
 * @property \App\Model\Table\BalanceTransactionsTable $BalanceTransactions
 * @property \App\Model\Table\BalanceSenderDetailsTable $BalanceSenderDetails
 * @property \App\Model\Table\BalanceReceiverDetailsTable $BalanceReceiverDetails
 * @property \App\Model\Table\BalanceTransactionDetailsTable $BalanceTransactionDetails
 * @property \App\Model\Table\VoucherTransactionsTable $VoucherTransactions
 * @property \App\Model\Table\RealmsTable $Realms
 * @property \App\Model\Table\VoucherTransactionSendDetailsTable $VoucherTransactionSendDetails
 * @property \App\Model\Table\VoucherTransactionReceivedDetailsTable $VoucherTransactionReceivedDetails
 * @method \App\Model\Entity\VoucherTransaction[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class VoucherTransactionsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null
     */

    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        $this->loadModel('Users');
        $this->loadModel('Realms');
        $this->loadModel('Profile');
        $this->loadModel('BalanceTransactions');
        $this->loadModel('BalanceSenderDetails');
        $this->loadModel('BalanceReceiverDetails');
        $this->loadModel('BalanceTransactionDetails');
        $this->loadModel('BalanceTransactionDetails');
        $this->loadModel('VoucherTransactions');
        $this->loadModel('VoucherTransactionSendDetails');
        $this->loadModel('VoucherTransactionReceivedDetails');
        $this->loadComponent('Aa');
        $this->loadComponent('RealmAcl');
        $this->loadComponent('Formatter');
    }

//------------------------------ Only for valid token----------------------------------
    function checkToken()
    {
        $user = $this->Aa->user_for_token($this);
        return $user['id'];
    }
//------------------------------ Only for valid token----------------------------------


//    ------------------------------ Checking for all validation voucher sender Start----------------------------------
    function checkSenderVoucher()
    {
        $idA = $this->VoucherTransactions
            ->find()
            ->where([
                'user_id' => $this->checkToken(),
                'profile_id' => $this->request->data('profile_id'),
                'realm_id' => $this->request->data('realm_id')
            ]);
        $id = 0;
        foreach ($idA as $row) {
            $id = $row->id;
        }
        return $id;
    }
//    ------------------------------ Checking for all validation voucher sender End----------------------------------


//    ------------------------------ Checking for exact voucher receiver Start----------------------------------


    function checkReceiverVoucher()
    {
        $idA = $this->VoucherTransactions
            ->find()
            ->where([
                'user_id' => $this->request->data('partner_user_id'),
                'profile_id' => $this->request->data('profile_id'),
                'realm_id' => $this->request->data('realm_id')
            ]);
        $id = 0;
        foreach ($idA as $row) {
            $id = $row->id;
        }
        return $id;
    }
//    ------------------------------ Checking for exact voucher receiver End----------------------------------


//-------------------------------Not needed any more---------------------------
    public function getUsers()
    {
        $this->request->allowMethod('get');

        $users = $this->Users->find()
            ->where(function ($exp) {
                return $exp->notEq('id', $this->checkToken());
            });

        $this->set([
            'users' => $users,
            '_serialize' => 'users'
        ]);
    }

// ----------------------------------Check for valid user Start---------------------------------
    function checkTransaction()
    {
        $idA = $this->Users->find()->select('id')
            ->where([
                'id' => $this->request->getData('partner_user_id')
            ]);

        $id = 0;
        foreach ($idA as $row) {
            $id = $row->id;
        }
        return $id;
    }
// ----------------------------------Check for valid user End---------------------------------


//    ---------------------------Is not necessary at this moment------------------------------
    function getUsername($user_id)
    {
        $user = $this->Users->find()->select('username')
            ->where(['id' => $user_id]);
        $username = null;

        foreach ($user as $row) {
            $username = $row->username;
        }
        return $username;
    }

//  -----------------------------------Getting id for sender balanceTransactions Start-------------------------------
    function checkSenderBalance(): int
    {
        $user = $this->Aa->user_for_token($this);
        if ($user) {
            $idA = $this->BalanceTransactions->find()->select('id')
                ->where([
                    'user_id' => $user['id']
                ]);
            $id = 0;
            foreach ($idA as $row) {
                $id = $row->id;
            }
            return $id;
        }
        return 0;
    }
//  -----------------------------------Getting id for sender balanceTransactions End-------------------------------

//  -----------------------------------Getting id for receiver balanceTransactions Start-------------------------------
    function checkReceiverBalance(): int
    {
        $idA = $this->BalanceTransactions->find()->select('id')
            ->where([
                'user_id' => $this->request->getData('partner_user_id')
            ]);
        $id = 0;
        foreach ($idA as $row) {
            $id = $row->id;
        }
        return $id;
    }
//  -----------------------------------Getting id for receiver balanceTransactions End-------------------------------


//    -------------------------------Configuring For voucher transaction Start----------------------------------------------
    private function generateDetails()
    {
        $tnx_id = $this->Formatter->random_alpha_numeric(10);
        $send = $this->VoucherTransactionSendDetails->newEntity();
        $send->set([
            'transaction' => $tnx_id,
            'user_id' => $this->request->getData('partner_user_id'),
            'sender_user_id' => $this->checkToken(),
            'profile_id' => $this->request->getData('profile_id'),
            'realm_id' => $this->request->getData('realm_id'),
            'balance' => sprintf('%0.2f', ($this->request->data('quantity_rate') * $this->request->getData('transfer_amount'))),
            'debit' => sprintf('%0.2f', $this->request->getData('transfer_amount')),
            'quantity_rate' => sprintf('%0.2f', $this->request->data('quantity_rate'))
        ]);

        $received = $this->VoucherTransactionReceivedDetails->newEntity();
        $received->set([
            'transaction' => $tnx_id,
            'receiver_user_id' => $this->request->getData('partner_user_id'),
            'user_id' => $this->checkToken(),
            'profile_id' => $this->request->getData('profile_id'),
            'realm_id' => $this->request->getData('realm_id'),
            'credit' => sprintf('%0.2f', $this->request->getData('transfer_amount')),
            'balance' => sprintf('%0.2f', $this->request->data('quantity_rate') * $this->request->getData('transfer_amount')),
            'quantity_rate' => sprintf('%0.2f', $this->request->data('quantity_rate'))
        ]);
        return $this->VoucherTransactionReceivedDetails->save($received) && $this->VoucherTransactionSendDetails->save($send);
    }

    private function generateDetailsAdmin()
    {
        $tnx_id = $this->Formatter->random_alpha_numeric(10);
        $received = $this->VoucherTransactionReceivedDetails->newEntity();
        $received->set([
            'transaction' => $tnx_id,
            'receiver_user_id' => $this->request->getData('partner_user_id'),
            'user_id' => $this->checkToken(),
            'profile_id' => $this->request->getData('profile_id'),
            'realm_id' => $this->request->getData('realm_id'),
            'credit' => sprintf('%0.2f', $this->request->getData('transfer_amount')),
            'balance' => sprintf('%0.2f', ($this->request->data('quantity_rate') * $this->request->getData('transfer_amount'))),
            'quantity_rate' => sprintf('%0.2f', $this->request->data('quantity_rate'))
        ]);
        return $this->VoucherTransactionReceivedDetails->save($received);
    }

    private function updateTransfer()
    {

        $send = $this->VoucherTransactions->get($this->checkSenderVoucher());
        $send_amount = $this->VoucherTransactions->patchEntity($send, $this->request->getData());
        $send_amount->set([
            'user_id' => $this->checkToken(),
            'realm_id' => $this->request->getData('realm_id'),
            'profile_id' => $this->request->getData('profile_id'),
            'debit' => sprintf('%0.2f', ($send_amount->get('debit') + $this->request->getData('transfer_amount'))),
            'balance' => sprintf('%0.2f', ($send_amount->get('balance') - $this->request->getData('transfer_amount'))),
            'quantity_rate' => sprintf('%0.2f', $this->request->getData('quantity_rate'))
        ]);

        $receive = $this->VoucherTransactions->get($this->checkReceiverVoucher());
        $receive_amount = $this->VoucherTransactions->patchEntity($receive, $this->request->getData());
        $receive_amount->set([
            'user_id' => $this->request->getData('partner_user_id'),
            'realm_id' => $this->request->getData('realm_id'),
            'profile_id' => $this->request->getData('profile_id'),
            'credit' => sprintf('%0.2f', ($receive_amount->get('credit') + $this->request->getData('transfer_amount'))),
            'balance' => sprintf('%0.2f', ($receive_amount->get('balance') + $this->request->getData('transfer_amount'))),
            'quantity_rate' => sprintf('%0.2f', $this->request->getData('quantity_rate'))
        ]);

//            ------------------------Balance should be greater tha 50 for activating the user----------------------
        if ($receive_amount->get('balance') >= 50) {
            $this->active($this->request->getData('partner_user_id'));
        }

        return $this->VoucherTransactions->save($send_amount) && $this->VoucherTransactions->save($receive_amount);
    }

    public function group()
    {

        if (isset($this->request->data['partner_user_id'])) {

            $ap_id = $this->request->data('partner_user_id');
            $id = $this->request->data['id'];

            try {

                $this->Acl->allow(
                    array('model' => 'Users', 'foreign_key' => $ap_id),
                    array('model' => 'Realms', 'foreign_key' => $id), 'create');

                $this->Acl->allow(
                    array('model' => 'Users', 'foreign_key' => $ap_id),
                    array('model' => 'Realms', 'foreign_key' => $id), 'read');

                $this->Acl->allow(
                    array('model' => 'Users', 'foreign_key' => $ap_id),
                    array('model' => 'Realms', 'foreign_key' => $id), 'update');

                $this->Acl->allow(
                    array('model' => 'Users', 'foreign_key' => $ap_id),
                    array('model' => 'Realms', 'foreign_key' => $id), 'delete');

                return true;

            } catch (\Exception $e) {
                return false;
            }
        }
    }


//            --------------------------------------- Active User First-------------------------------

    /**
     * Activate user
     * @param $user_id
     * @return
     */
    private function active($user_id)
    {
        $active_user = $this->Users->get($user_id);
        $active_user->set([
            'active' => true
        ]);
        $this->Users->save($active_user);
    }

    private function insertTransfer()
    {
        if ($this->insertBalanceDetails() && $this->group()) {
//                        ---------------------------Creating new voucher transactions---------------------
//                        ------------------------------Preparing entity for receiver-----------------
            $receive_amount = $this->VoucherTransactions->newEntity();
            $receive_amount->set([
                'user_id' => $this->request->getData('partner_user_id'),
                'realm_id' => $this->request->getData('realm_id'),
                'profile_id' => $this->request->getData('profile_id'),
                'credit' => sprintf('%0.2f', ($receive_amount->get('credit') + $this->request->getData('transfer_amount'))),
                'balance' => sprintf('%0.2f', $this->request->getData('transfer_amount')),
                'quantity_rate' => sprintf('%0.2f', $this->request->getData('quantity_rate'))
            ]);

//          --------------------------Checking for new user for activate---------------------------
//            ------------------------Balance should be greater tha 50 for activating the user----------------------
            if ($receive_amount->get('balance') >= 50) {
                $this->active($this->request->getData('partner_user_id'));
            }


            //                        ---------------Update entity for Sender-----------------
            $send = $this->VoucherTransactions->get($this->checkSenderVoucher());
            $send_amount = $this->VoucherTransactions->patchEntity($send, $this->request->getData());
            $send_amount->set([
                'user_id' => $this->checkToken(),
                'realm_id' => $this->request->getData('realm_id'),
                'profile_id' => $this->request->getData('profile_id'),
                'debit' => sprintf('%0.2f', ($send_amount->get('debit') + $this->request->getData('transfer_amount'))),
                'balance' => sprintf('%0.2f', ($send_amount->get('balance') - $this->request->getData('transfer_amount'))),
                'quantity_rate' => sprintf('%0.2f', $this->request->getData('quantity_rate'))
            ]);

            return $this->VoucherTransactions->save($receive_amount) && $this->VoucherTransactions->save($send_amount);
        } else {
            return false;
        }

    }

    //-----------------------------------BalanceTransactionDetails---------------------------------------
    private function defineBalanceDetails()
    {
        $tnx_id = $this->Formatter->random_alpha_numeric(10);

        $balance = $this->BalanceTransactionDetails->newEntity();
        $balance->set([
            'transaction' => $tnx_id,
            'user_id' => $this->checkToken(),
            'receiver_user_id' => $this->request->getData('partner_user_id'),
            'profile_id' => $this->request->getData('profile_id'),
            'realm_id' => $this->request->getData('realm_id'),
            'vouchers' => $this->request->getData('transfer_amount'),
            'quantity_rate' => sprintf('%0.2f', $this->request->getData('quantity_rate')),
            'total' => sprintf('%0.2f', ($this->request->getData('transfer_amount') * $this->request->getData('quantity_rate')))
        ]);

        return $this->BalanceTransactionDetails->save($balance);
    }

    private function insertBalanceDetails()
    {
        if ($this->insertBalance() && $this->defineBalanceDetails()) {
            return true;
        } else {
            return false;
        }
    }
//    -------------------------------Configuring For balance transaction End----------------------------------------------


//    -------------------------------Configuring For balance transaction Start----------------------------------------------
    private function insertBalance()
    {
        $balanceTransactionSender = $this->BalanceTransactions->get($this->checkSenderBalance());

        $balanceTransactionSender->set([
            'receivable' => sprintf('%0.2f', ($this->request->getData('transfer_amount') * $this->request->getData('quantity_rate')
                + $balanceTransactionSender->get('receivable'))),
            'payable' => sprintf('%0.2f', $balanceTransactionSender->get('payable')),
        ]);

        $balanceTransactionReceiver = $this->BalanceTransactions->get($this->checkReceiverBalance());

        $balanceTransactionReceiver->set([
            'payable' => $this->request->getData('transfer_amount') * $this->request->getData('quantity_rate')
                + $balanceTransactionReceiver->get('payable'),
            'receivable' => sprintf('%0.2f', $balanceTransactionReceiver->get('receivable'))
        ]);

        return $this->BalanceTransactions->save($balanceTransactionSender) && $this->BalanceTransactions->save($balanceTransactionReceiver);
    }

    public function index()
    {
        $this->request->allowMethod('get');

        $user_id = $this->checkToken();

        if ($user_id) {
            $user = $this->Users->get($user_id);
            if (!$user->get('parent_id')) {
                $voucher_tx = $this->VoucherTransactions
                    ->find()
                    ->where([
                        'Users.id' => $user_id
                    ])
                    ->contain(['Users', 'Profiles', 'Realms']);

                $total = $voucher_tx->count();

                $item = $this->Formatter->pagination($voucher_tx);

                $this->set([
                    'item' => $item,
                    'totalCount' => $total,
                    'success' => true,
                    '_serialize' => ['item', 'totalCount', 'success']
                ]);
            } else {
                $voucher_tx = $this->VoucherTransactions
                    ->find()
                    ->where(['Users.id' => $this->checkToken()])
                    ->contain(['Users', 'Profiles', 'Realms']);

                $total = $voucher_tx->count();

                $item = $this->Formatter->pagination($voucher_tx);

                $this->set([
                    'item' => $item,
                    'totalCount' => $total,
                    'success' => true,
                    '_serialize' => ['item', 'totalCount', 'success']
                ]);
            }
        } else {
            $this->set([
                'message' => 'Invalid user account',
                'status' => false,
                '_serialize' => ['message', 'status']
            ]);
        }
    }


    //    -------------------------------Configuring For balance transaction End----------------------------------------------

    public function view()
    {
        $this->request->allowMethod('get');
        $user_id = $this->checkToken();
        if ($user_id) {
            $user = $this->Users->get($user_id);
            if (!$user->get('parent_id')) {
                $key = $this->VoucherTransactions->get($this->request->query('key'));

                $send_items = $this->VoucherTransactionSendDetails
                    ->find()
                    ->where([
                        'realm_id' => $key->get('realm_id'),
                        'profile_id' => $key->get('profile_id')
                    ])
                    ->contain(['Users', 'Realms', 'Profiles']);

                $send_total = $send_items->count();

                $send_item = $this->Formatter->pagination($send_items);


                $received_items = $this->VoucherTransactionReceivedDetails
                    ->find()
                    ->where([
                        'receiver_user_id' => $key->get('user_id'),
                        'realm_id' => $key->get('realm_id'),
                        'profile_id' => $key->get('profile_id')
                    ])
                    ->contain(['Users', 'Realms', 'Profiles']);

                $received_total = $received_items->count();

                $received_item = $this->Formatter->pagination($received_items);

                $this->set([
                    'send' => $send_item,
                    'send_total' => $send_total,
                    'received' => $received_item,
                    'received_total' => $received_total,
                    '_serialize' => ['send', 'send_total', 'received', 'received_total']
                ]);

            } else {
                $key = $this->VoucherTransactions->get($this->request->query('key'));

                $send_items = $this->VoucherTransactionSendDetails
                    ->find()
                    ->where([
                        'sender_user_id' => $user_id,
                        'realm_id' => $key->get('realm_id'),
                        'profile_id' => $key->get('profile_id')
                    ])
                    ->contain(['Users', 'Realms', 'Profiles']);

                $send_total = $send_items->count();

                $send_item = $this->Formatter->pagination($send_items);

                $received_items = $this->VoucherTransactionReceivedDetails
                    ->find()
                    ->where([
                        'receiver_user_id' => $key->get('user_id'),
                        'realm_id' => $key->get('realm_id'),
                        'profile_id' => $key->get('profile_id')
                    ])
                    ->contain(['Users', 'Realms', 'Profiles']);

                $received_total = $received_items->count();

                $received_item = $this->Formatter->pagination($received_items);

                $this->set([
                    'send' => $send_item,
                    'send_total' => $send_total,
                    'received' => $received_item,
                    'received_total' => $received_total,
                    '_serialize' => ['send', 'send_total', 'received', 'received_total']
                ]);
            }
        } else {
            $this->set([
                'message' => 'Invalid token',
                'status' => false,
                '_serialize' => ['status', 'message']
            ]);
        }
    }


    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     * @throws \Exception
     */
    public function add()
    {
        $this->request->allowMethod('post');

//        -------------------Check Valid token----------------
        if ($this->request->query('token') && $this->checkToken()) {
            if ($this->checkSenderVoucher()) {
                $transfer_amount = $this->VoucherTransactions->get($this->checkSenderVoucher());
//            ---------------------------- Check Amount --------------------
                if ($transfer_amount->get('balance') >= $this->request->getData('transfer_amount')) {
//                ------------------------ Check Partner ------------------------------
                    if ($this->checkTransaction()) {
//                    --------------------- Checking for realm & profile ----------------
                        if ($this->checkReceiverVoucher()) {
//                        ---------------Preparing entity for receiver-----------------

                            if ($this->updateTransfer() && $this->generateDetails()) {

                                if ($this->insertBalanceDetails()) {
                                    $this->set([
                                        'message' => 'Transaction successful',
                                        'success' => true,
                                        '_serialize' => ['success', 'message']
                                    ]);
                                } else {
                                    $this->set([
                                        'message' => 'Failed to generate balance contact with admin',
                                        'success' => false,
                                        '_serialize' => ['success', 'message']
                                    ]);
                                }

                            } else {
                                $this->set([
                                    'message' => 'Transfer failed',
                                    'success' => false,
                                    '_serialize' => ['success', 'message']
                                ]);
                            }
                        } else {
                            if ($this->insertTransfer() && $this->generateDetails()) {
                                $this->set([
                                    'message' => 'Transaction successful',
                                    'success' => true,
                                    '_serialize' => ['success', 'message']
                                ]);

                            } else {
                                $this->set([
                                    'message' => 'Transfer failed',
                                    'success' => false,
                                    '_serialize' => ['success', 'message']
                                ]);
                            }
                        }
                    } else {
                        $this->set([
                            'message' => 'Invalid partner account',
                            'success' => false,
                            '_serialize' => ['success', 'message']
                        ]);
                    }
                } else {
                    $this->set([
                            'message' => 'You do not have enough balance',
                            'success' => false,
                            '_serialize' => ['success', 'message']]
                    );
                }
            } else {
                $this->set([
                        'message' => 'You do not have this profile balance',
                        'success' => false,
                        '_serialize' => ['success', 'message']]
                );
            }
        } else {
            $this->set([
                    'token' => 'Missing token',
                    'success' => false,
                    '_serialize' => ['success', 'token']]
            );
        }
    }


//-----------------------------------Generate voucher for admin only Start-------------------------------------------
    public function generate()
    {
        $this->request->allowMethod('post');
        $user = $this->Users->get($this->checkToken());
        if (!$user->get('parent_id')) {
            if ($this->checkSenderVoucher()) {
                $updateVoucher = $this->VoucherTransactions->get($this->checkSenderVoucher());
                $updateVoucher = $this->VoucherTransactions->patchEntity($updateVoucher, $this->request->getData());

                $newBalance = $updateVoucher->set([
                    'user_id' => $this->checkToken(),
                    'profile_id' => $this->request->data('profile_id'),
                    'realm_id' => $this->request->data('realm_id'),
                    'credit' => sprintf('%0.2f', ($this->request->data('transfer_amount') + $updateVoucher->get('credit'))),
                    'debit' => sprintf('%0.2f', $updateVoucher->get('debit')),
                    'balance' => sprintf('%0.2f', ($this->request->data('transfer_amount') + $updateVoucher->get('balance'))),
                    'quantity_rate' => sprintf('%0.2f', $this->request->data('quantity_rate'))
                ]);
                if ($this->VoucherTransactions->save($newBalance) && $this->generateDetailsAdmin()) {
                    $this->set([
                        'message' => 'Generate balance successful',
                        'success' => true,
                        '_serialize' => ['success', 'message']
                    ]);
                } else {
                    $this->set([
                        'message' => 'Failed to update balance',
                        'success' => false,
                        '_serialize' => ['success', 'message']
                    ]);
                }


            } else {
                $updateVoucher = $this->VoucherTransactions->newEntity();

                $newBalance = $updateVoucher->set([
                    'user_id' => $this->checkToken(),
                    'profile_id' => $this->request->data('profile_id'),
                    'realm_id' => $this->request->data('realm_id'),
                    'credit' => sprintf('%0.2f', ($this->request->data('transfer_amount') + $updateVoucher->get('credit'))),
                    'balance' => sprintf('%0.2f', ($this->request->data('transfer_amount') + $updateVoucher->get('balance'))),
                    'quantity_rate' => sprintf('%0.2f', $this->request->data('quantity_rate'))
                ]);
                if ($this->VoucherTransactions->save($newBalance) && $this->generateDetailsAdmin()) {
                    $this->set([
                        'message' => 'Balance generate successful',
                        'success' => true,
                        '_serialize' => ['success', 'message']
                    ]);
                } else {
                    $this->set([
                        'message' => 'Failed to generate balance',
                        'success' => false,
                        '_serialize' => ['success', 'message']
                    ]);
                }

            }
        } else {
            $this->set([
                'message' => 'Invalid token',
                'success' => false,
                '_serialize' => ['success', 'message']
            ]);
        }
    }
//-----------------------------------Generate voucher for admin only End-------------------------------------------


//---------------------------------- Refund ---------------------------------------------

    public function refund()
    {
        $this->request->allowMethod('POST');

        $user_id = $this->checkToken();
        if ($user_id) {
            $data = $this->request->data();

            $owner = $this->Users->get($data['partner_user_id']);

            if ($owner) {
                $sender = $this->VoucherTransactions
                    ->find()
                    ->where([
                        'user_id' => $user_id,
                        'realm_id' => $data['realm_id'],
                        'profile_id' => $data['profile_id']
                    ])
                    ->first();

                if ($data['transfer_amount'] <= $sender['balance']) {
                    $receiver = $this->VoucherTransactions
                        ->find()
                        ->where([
                            'user_id' => $owner['id'],
                            'realm_id' => $data['realm_id'],
                            'profile_id' => $data['profile_id']
                        ])
                        ->first();

                    $updateOwnerVoucher = $this->VoucherTransactions->get($receiver['id']);
                    $updateOwnerCredit = $this->VoucherTransactions->patchEntity($updateOwnerVoucher, $data);

                    $newOwnerCredit = $updateOwnerCredit->set([
                        'user_id' => $updateOwnerVoucher->user_id,
                        'profile_id' => $updateOwnerVoucher->profile_id,
                        'realm_id' => $updateOwnerVoucher->realm_id,
                        'credit' => $data['transfer_amount'] + $updateOwnerVoucher->credit,
                        'debit' => $updateOwnerVoucher->debit - $data['transfer_amount'],
                        'balance' => $updateOwnerVoucher->balance + $data['transfer_amount'],
                        'quantity_rate' => 0
                    ]);

                    $updateSellerVoucher = $this->VoucherTransactions->get($sender['id']);
                    $updateSenderCredit = $this->VoucherTransactions->patchEntity($updateSellerVoucher, $data);

                    $newSenderCredit = $updateSenderCredit->set([
                        'user_id' => $updateSenderCredit->user_id,
                        'profile_id' => $updateSenderCredit->profile_id,
                        'realm_id' => $updateSenderCredit->realm_id,
                        'credit' => $updateSenderCredit->credit - $data['transfer_amount'],
                        'debit' => $updateSenderCredit->debit + $data['transfer_amount'],
                        'balance' => $updateSenderCredit->balance - $data['transfer_amount'],
                        'quantity_rate' => 0
                    ]);

                    if ($this->generateDetails()) {
                        if ($this->VoucherTransactions->save($newOwnerCredit) && $this->VoucherTransactions->save($newSenderCredit)) {
                            $this->set([
                                'message' => 'Your refund is successful',
                                'success' => true,
                                '_serialize' => ['success', 'message']
                            ]);
                        } else {
                            $this->set([
                                'message' => 'Failed to refund',
                                'success' => false,
                                '_serialize' => ['success', 'message']
                            ]);
                        }
                    } else {
                        $this->set([
                            'message' => 'Generate details unsuccessful',
                            'success' => false,
                            '_serialize' => ['success', 'message']
                        ]);
                    }


                } else {
                    $this->set([
                        'message' => 'You don not have enough credit',
                        'success' => false,
                        '_serialize' => ['success', 'message']
                    ]);
                }

            } else {
                $this->set([
                    'message' => 'Your owner is not available',
                    'success' => false,
                    '_serialize' => ['success', 'message']
                ]);
            }

        } else {
            $this->set([
                'message' => 'Invalid token',
                'success' => false,
                '_serialize' => ['success', 'message']
            ]);
        }
    }

}
