<?php

namespace App\Controller;

use App\Controller\AppController;
use Cake\Core\Configure;
use Cake\Database\Exception;
use Cake\Log\Log;

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
    private function checkSenderCredits($sender_id)
    {
        return $this->VoucherTransactions
            ->find()
            ->where([
                'user_id' => $sender_id,
                'profile_id' => $this->request->data('profile_id'),
                'realm_id' => $this->request->data('realm_id')
            ])->first();
    }
//    ------------------------------ Checking for all validation voucher sender End----------------------------------


//    ------------------------------ Checking for exact voucher receiver Start----------------------------------


    private function checkReceiverCredits($receiver_id)
    {
        return $this->VoucherTransactions
            ->find()
            ->where([
                'user_id' => $receiver_id,
                'profile_id' => $this->request->data('profile_id'),
                'realm_id' => $this->request->data('realm_id')
            ])->first();
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
    function validateTransferReceiver($receiver_id, $sender_id)
    {
        return $this->Users->find()->select('id')
            ->where([
                'id' => $receiver_id,
                'parent_id' => $sender_id
            ]);
    }

    function validateRefundReceiver($receiver_id, $sender_id)
    {
        return $this->Users->find()->select('id')
            ->where([
                'id' => $sender_id,
                'parent_id' => $receiver_id
            ]);
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
    private function saveDetailsTransactions($sender_id, $receiver_id)
    {

        $data = $this->request->data();

        $tnx_id = $this->Formatter->random_alpha_numeric(10);
        $send = $this->VoucherTransactionSendDetails->newEntity();
        $send->set([
            'transaction' => $tnx_id,
            'user_id' => $receiver_id,
            'sender_user_id' => $sender_id,
            'profile_id' => $data['profile_id'],
            'realm_id' => $data['realm_id'],
            'debit' => $data['transfer_amount'],
            'credit' => 0
        ]);

        $received = $this->VoucherTransactionReceivedDetails->newEntity();
        $received->set([
            'transaction' => $tnx_id,
            'receiver_user_id' => $receiver_id,
            'user_id' => $sender_id,
            'profile_id' => $data['profile_id'],
            'realm_id' => $data['realm_id'],
            'credit' => $data['transfer_amount'],
            'debit' => 0
        ]);

        return $this->VoucherTransactionReceivedDetails->save($received) && $this->VoucherTransactionSendDetails->save($send);
    }

    private function saveAdminDetailsTransactions($user_id)
    {
        $tnx_id = $this->Formatter->random_alpha_numeric(10);
        $received = $this->VoucherTransactionReceivedDetails->newEntity();
        $received->set([
            'transaction' => $tnx_id,
            'receiver_user_id' => $user_id,
            'user_id' => $user_id,
            'profile_id' => $this->request->getData('profile_id'),
            'realm_id' => $this->request->getData('realm_id'),
            'credit' => sprintf('%0.2f', $this->request->getData('transfer_amount')),
            'quantity_rate' => sprintf('%0.2f', $this->request->data('quantity_rate'))
        ]);
        return $this->VoucherTransactionReceivedDetails->save($received);
    }

    private function updateTransfer($sender_transaction, $receiver_transaction)
    {

        $this->saveDetailsTransactions($sender_transaction->user_id, $receiver_transaction->user_id);

        $sender_transaction->set([
            'debit' => sprintf('%0.2f', ($sender_transaction->get('debit') + $this->request->getData('transfer_amount'))),
            'balance' => sprintf('%0.2f', ($sender_transaction->get('balance') - $this->request->getData('transfer_amount'))),
            'quantity_rate' => sprintf('%0.2f', $this->request->getData('quantity_rate'))
        ]);

        $receiver_transaction->set([
            'credit' => sprintf('%0.2f', ($receiver_transaction->get('credit') + $this->request->getData('transfer_amount'))),
            'balance' => sprintf('%0.2f', ($receiver_transaction->get('balance') + $this->request->getData('transfer_amount'))),
            'quantity_rate' => sprintf('%0.2f', $this->request->getData('quantity_rate'))
        ]);

//            ------------------------Balance should be greater tha 50 for activating the user----------------------
        if ($receiver_transaction->get('balance') >= 50) {
            $this->active($this->request->getData('partner_user_id'));
        }

        return $this->VoucherTransactions->save($sender_transaction) && $this->VoucherTransactions->save($receiver_transaction);
    }

    public function enableRealmAccess()
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

    private function insertTransfer($user_id, $sender_transaction)
    {
        //provide access to the realm
        $this->enableRealmAccess();

        //Update entity for Sender
        $sender_transaction->set([
            'debit' => sprintf('%0.2f', ($sender_transaction->get('debit') + $this->request->getData('transfer_amount'))),
            'balance' => sprintf('%0.2f', ($sender_transaction->get('balance') - $this->request->getData('transfer_amount'))),
            'quantity_rate' => sprintf('%0.2f', $this->request->getData('quantity_rate'))
        ]);

        //Create new entity for Receiver
        $receiver_transaction = $this->VoucherTransactions->newEntity();
        $receiver_transaction->set([
            'user_id' => $this->request->getData('partner_user_id'),
            'realm_id' => $this->request->getData('realm_id'),
            'profile_id' => $this->request->getData('profile_id'),
            'credit' => sprintf('%0.2f', $this->request->getData('transfer_amount')),
            'balance' => sprintf('%0.2f', $this->request->getData('transfer_amount')),
            'quantity_rate' => sprintf('%0.2f', $this->request->getData('quantity_rate'))
        ]);

        //save details transactions
        $this->saveDetailsTransactions($sender_transaction->user_id, $receiver_transaction->user_id);

        return $this->VoucherTransactions->save($sender_transaction) && $this->VoucherTransactions->save($receiver_transaction);
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
//                -------------For admin Section----------------
                $voucher_tx = $this->VoucherTransactions
                    ->find()
//                    ->where([
//                        'Users.id' => $user_id
//                    ])
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
//                -------------For Reseller------------------
                $resellers = $this->Users->find()
                    ->where([
                        'parent_id' => $user_id
                    ])
                    ->select('id');

                $users = array();
                array_push($users, $user_id);


                foreach ($resellers as $item) {
                    array_push($users, $item->id);
                }


                $voucher_tx = $this->VoucherTransactions
                    ->find()
                    ->whereInList('Users.id', $users)
                    ->select([
                        'VoucherTransactions.id',
                        'VoucherTransactions.user_id',
                        'VoucherTransactions.credit',
                        'VoucherTransactions.debit',
                        'VoucherTransactions.balance',
                        'Users.username',
                        'Profiles.name',
                        'Realms.name',
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


    public function sendCredit()
    {
        $this->request->allowMethod('GET');
        $user = $this->Aa->user_for_token($this);
        if (!$user) {
            return;
        }
        $key = $this->VoucherTransactions->get($this->request->query('key'));

        $send_items = $this->VoucherTransactionSendDetails
            ->find()
            ->where([
                'sender_user_id' => $key->get('user_id'),
                'realm_id' => $key->get('realm_id'),
                'profile_id' => $key->get('profile_id')
            ])
            ->contain(['Users', 'Realms', 'Profiles']);

        $send_total = $send_items->count();

        $item = $this->Formatter->pagination($send_items);
        $this->set([
            'success' => true,
            'item' => $item,
            'total' => $send_total,
            '_serialize' => ['success', 'item', 'total']
        ]);
    }

    public function receivedCredit()
    {
        $this->request->allowMethod('GET');
        $user = $this->Aa->user_for_token($this);
        if (!$user) {
            return;
        }
        $key = $this->VoucherTransactions->get($this->request->query('key'));

        $received_items = $this->VoucherTransactionReceivedDetails
            ->find()
            ->where([
                'receiver_user_id' => $key->get('user_id'),
                'realm_id' => $key->get('realm_id'),
                'profile_id' => $key->get('profile_id')
            ])
            ->contain(['Users', 'Realms', 'Profiles']);

        $received_total = $received_items->count();

        $item = $this->Formatter->pagination($received_items);
        $this->set([
            'success' => true,
            'item' => $item,
            'total' => $received_total,
            '_serialize' => ['success', 'item', 'total']
        ]);
    }
    /*
        public function view()
        {
            $this->request->allowMethod('get');
            $user_id = $this->checkToken();
            if ($user_id) {
                $user = $this->Users->get($user_id);
                if (!$user->get('parent_id')) {
    //                ------------For Admin Section-----------------
                    $key = $this->VoucherTransactions->get($this->request->query('key'));

                    $send_items = $this->VoucherTransactionSendDetails
                        ->find()
                        ->where([
                            'sender_user_id' => $key->get('user_id'),
                            'realm_id' => $key->get('realm_id'),
                            'profile_id' => $key->get('profile_id')
                        ])
                        ->contain(['Users', 'Realms', 'Profiles']);

                    $send_total = $send_items->count();

                    $send_item = $this->Formatter->pagination($send_items);


                    $received_items = $this->VoucherTransactionReceivedDetails
                        ->find()
                        ->where([
                            'Users.id' => $user_id,
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
    */


    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     * @throws \Exception
     */
    public function add()
    {
        $this->request->allowMethod('post');

        $user = $this->Aa->user_for_token($this);
        if (!$user) {
            return;
        }

        $user_id = $user['id'];

        // check balace for requested realm and profile
        $sender_transaction = $this->checkSenderCredits($user_id);

        if ($sender_transaction) {
            //Check available blance
            if ($sender_transaction->get('balance') >= $this->request->getData('transfer_amount')) {
                //Validate Receiver
                if ($this->validateTransferReceiver($this->request->getData('partner_user_id'), $user_id)) {
                    // Checking existing credits for same realm & profile
                    $receiver_transaction = $this->checkReceiverCredits($this->request->getData('partner_user_id'));
                    if ($receiver_transaction) {
                        if ($this->updateTransfer($sender_transaction, $receiver_transaction)) {
                            //todo: need to add balance details later
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
                    } else {
                        if ($this->insertTransfer($user_id, $sender_transaction)) {
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
                        'message' => 'Invalid receiver!',
                        'success' => false,
                        '_serialize' => ['success', 'message']
                    ]);
                }
            } else {
                $this->set([
                        'message' => 'You do not have enough credits!',
                        'success' => false,
                        '_serialize' => ['success', 'message']]
                );
            }
        } else {
            $this->set([
                    'message' => 'You do not have credits!',
                    'success' => false,
                    '_serialize' => ['success', 'message']]
            );
        }
    }


//-----------------------------------Generate voucher for admin only Start-------------------------------------------
    public function generate()
    {
        $this->request->allowMethod('post');

        $user = $this->Aa->user_for_token($this);
        if (!$user) {
            return;
        }
        $user_id = $user['id'];

        if ($this->Aa->admin_check($this)) {
            // check balace for requested realm and profile
            $existing_credit = $this->checkSenderCredits($user_id);
            if ($existing_credit) {
                if ($this->updateAdminCredits($user_id, $existing_credit)) {
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
                if ($this->insertAdminCredit($user_id)) {
                    $this->set([
                        'message' => 'Balance generated successfully.',
                        'success' => true,
                        '_serialize' => ['success', 'message']
                    ]);
                } else {
                    $this->set([
                        'message' => 'Failed to generate balance.',
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

    private function updateAdminCredits($user_id, $receiver_transaction)
    {

        $this->saveAdminDetailsTransactions($user_id);

        $newBalance = $receiver_transaction->set([
            'credit' => sprintf('%0.2f', ($this->request->data('transfer_amount') + $receiver_transaction->get('credit'))),
            'debit' => sprintf('%0.2f', $receiver_transaction->get('debit')),
            'balance' => sprintf('%0.2f', ($this->request->data('transfer_amount') + $receiver_transaction->get('balance'))),
            'quantity_rate' => sprintf('%0.2f', $this->request->data('quantity_rate'))
        ]);

        return $this->VoucherTransactions->save($newBalance);
    }


    private function insertAdminCredit($user_id)
    {
        //save details transactions
        $this->saveAdminDetailsTransactions($user_id);

        $receiver_transaction = $this->VoucherTransactions->newEntity();
        $receiver_transaction->set([
            'user_id' => $user_id,
            'realm_id' => $this->request->getData('realm_id'),
            'profile_id' => $this->request->getData('profile_id'),
            'credit' => sprintf('%0.2f', $this->request->getData('transfer_amount')),
            'balance' => sprintf('%0.2f', $this->request->getData('transfer_amount')),
            'quantity_rate' => sprintf('%0.2f', $this->request->getData('quantity_rate'))
        ]);

        return $this->VoucherTransactions->save($receiver_transaction);
    }


//---------------------------------- Refund ---------------------------------------------

    public function refund()
    {
        $this->request->allowMethod('post');

        $user = $this->Aa->user_for_token($this);
        if (!$user) {
            return;
        }

        $user_id = $user['id'];

        $data = $this->request->data();

        // check balace for requested realm and profile
        $sender_transaction = $this->checkSenderCredits($data['partner_user_id']);

        if ($sender_transaction) {
            //Check available blance
            if ($sender_transaction->get('balance') >= $this->request->getData('transfer_amount')) {
                //Validate Receiver
                if ($this->validateRefundReceiver($user_id, $this->request->getData('partner_user_id'))) {
                    // Checking existing credits for same realm & profile
                    $receiver_transaction = $this->checkReceiverCredits($user_id);
                    if ($receiver_transaction) {
                        if ($this->updateTransfer($sender_transaction, $receiver_transaction)) {
                            //todo: need to add balance details later
                            $this->set([
                                'message' => 'Refund successful',
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
                    } else {
                        $this->set([
                            'message' => 'You are not able to refund this credit!',
                            'success' => false,
                            '_serialize' => ['success', 'message']
                        ]);
                    }
                } else {
                    $this->set([
                        'message' => 'Invalid receiver!',
                        'success' => false,
                        '_serialize' => ['success', 'message']
                    ]);
                }
            } else {
                $this->set([
                        'message' => 'Your reseller has not enough credits!',
                        'success' => false,
                        '_serialize' => ['success', 'message']]
                );
            }
        } else {
            $this->set([
                    'message' => 'Your reseller has no credits!',
                    'success' => false,
                    '_serialize' => ['success', 'message']]
            );
        }
    }

    public function profileAp()
    {
        $user = $this->_ap_right_check();
        if (!$user) {
            return;
        }

        $item = array();
        $transactions = $this->VoucherTransactions->find()
            ->where([
                'Users.id' => $user['id']
            ])
            ->contain(['Users', 'Profiles'])
//            ->select(['profile_id', 'profile_name'])
        ;
        foreach ($transactions as $i){
            array_push($item, $i->profile);
        }

        $this->set(array(
            'items' => $item,
            'success' => true,
            '_serialize' => array('items', 'success')
        ));
    }

}
