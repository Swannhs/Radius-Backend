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
        $this->loadComponent('Transaction');
    }

    function checkToken()
    {
        $user = $this->Aa->user_for_token($this);
        return $user['id'];
    }

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

    function validateTransferPartners($sender_id, $receiver_id)
    {
        return $this->Users->find()->select('id')
            ->where([
                'id' => $receiver_id,
                'parent_id' => $sender_id
            ]);
    }

    function validateRefundPartners($sender_id, $receiver_id)
    {
        return $this->Users->find()->select('id')
            ->where([
                'id' => $sender_id,
                'parent_id' => $receiver_id
            ]);
    }

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


    public function index()
    {
        $this->request->allowMethod('get');

        $user = $this->Aa->user_for_token($this);
        if (!$user) {
            return;
        }
        $user_id = $user['id'];

        if ($this->Aa->admin_check($this)) {
//                -------------For admin Section----------------
            $voucher_tx = $this->VoucherTransactions
                ->find()
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

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     * @throws \Exception
     */
    public function transfer()
    {
        $this->request->allowMethod('post');

        $user = $this->Aa->user_for_token($this);
        if (!$user) {
            return;
        }

        $sender_id = $user['id'];
        if(isset($this->request->data['partner_user_id'])){
            $receiver_id = $this->request->data['partner_user_id'];
            //Validate Receiver
            if ($this->validateTransferPartners($sender_id, $receiver_id)) {
                $transaction_response = $this->Transaction->transfer($sender_id, $receiver_id);
                if($transaction_response['success']){
                    if($transaction_response['type'] == 'insert'){
                        $this->enableRealmAccess();
                        $this->active($this->request->data['partner_user_id']); //active in first balance transfer
                    }
                }
                $this->set($transaction_response);
            } else {
                $this->set([
                    'message' => 'Invalid receiver!',
                    'success' => false,
                    '_serialize' => ['success', 'message']
                ]);
            }
        } else {
            $this->set([
                'message' => 'No receiver found!',
                'success' => false,
                '_serialize' => ['success', 'message']
            ]);
        }
    }

    //---------------------------------- Refund ---------------------------------------------

    public function refund()
    {
        $this->request->allowMethod('post');

        $user = $this->Aa->user_for_token($this);
        if (!$user) {
            return;
        }

        $receiver_id = $user['id'];
        if(isset($this->request->data['partner_user_id'])){
            $sender_id = $this->request->data['partner_user_id'];
            //Validate Receiver
            if ($this->validateRefundPartners($sender_id, $receiver_id)) {
                $this->set(
                    $this->Transaction->transfer($sender_id, $receiver_id)
                );
            } else {
                $this->set([
                    'message' => 'Invalid sender!',
                    'success' => false,
                    '_serialize' => ['success', 'message']
                ]);
            }
        } else {
            $this->set([
                'message' => 'No sender found!',
                'success' => false,
                '_serialize' => ['success', 'message']
            ]);
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
            $existing_credit = $this->Transaction->checkBalance($user_id);
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

    public function profileAp()
    {
        $user = $this->Aa->user_for_token($this);
        if (!$user) {
            return;
        }

        $item = array();
        $transactions = $this->VoucherTransactions->find()
            ->where([
                'Users.id' => $user['id']
            ])
            ->select(['Profiles.id', 'Profiles.name'])
            ->group('Profiles.id')
            ->contain(['Users', 'Profiles']);

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
