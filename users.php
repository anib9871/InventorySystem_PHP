<?php
  $page_title = 'Users Management';
  require_once('includes/load.php');

  // page_require_level(1);
  $org_id = isset($_SESSION['org_id']) ? (int)$_SESSION['org_id'] : 0;

  /* ================= ADD USER LOGIC ================= */
  if(isset($_POST['add_user'])){
      $req_fields = array('full-name','username','password','confirm_password','level');
      validate_fields($req_fields);

      if(empty($errors)){
          $name = remove_junk($db->escape($_POST['full-name']));
          $username = remove_junk($db->escape($_POST['username']));
          $password = $_POST['password'];
          $confirm = $_POST['confirm_password'];
          
          $user_level = (int)$db->escape($_POST['level']);
          $center_id = (int)$db->escape($_POST['center_id']);
          $org_id_int = (int)$org_id;

          $check = find_by_sql("SELECT username FROM users WHERE username='{$username}'");
          if($check){
              $session->msg('d',"Username already exists");
              redirect('users.php');
          }

          if($password != $confirm){
              $session->msg('d',"Password not matched");
              redirect('users.php');
          }

          $menu_masters = isset($_POST['menu_masters']) ? 1 : 0;
          $menu_transaction = isset($_POST['menu_transaction']) ? 1 : 0;
          $menu_payments = isset($_POST['menu_payments']) ? 1 : 0;
          $menu_reports = isset($_POST['menu_reports']) ? 1 : 0;

          $sub_perms_string = '';
          if(isset($_POST['sub_perms']) && !empty($_POST['sub_perms'])){
              $clean_perms = array_map(function($p) use ($db) {
                  return remove_junk($db->escape(trim($p)));
              }, $_POST['sub_perms']);
              $sub_perms_string = implode(',', $clean_perms);
          }

          $query = "INSERT INTO users (
              name, username, password, user_level, status, org_id, center_id,
              menu_masters, menu_transaction, menu_payments, menu_reports, sub_permissions
          ) VALUES (
              '{$name}', '{$username}', '{$password}', {$user_level}, 1, {$org_id_int}, {$center_id},
              {$menu_masters}, {$menu_transaction}, {$menu_payments}, {$menu_reports}, '{$sub_perms_string}'
          )";

          if($db->query($query)){
              $session->msg('s',"User account created successfully");
          }else{
              $session->msg('d',"Failed to create user");
          }
          redirect('users.php');
      }
  }

  /* ================= UPDATE USER LOGIC ================= */
  if(isset($_POST['update_user']) && isset($_GET['edit'])){
      $id = (int)$_GET['edit'];
      $name = remove_junk($db->escape($_POST['full-name']));
      $username = remove_junk($db->escape($_POST['username']));
      
      $level = (int)$db->escape($_POST['level']);
      $center_id = (int)$db->escape($_POST['center_id']);
      $password = $_POST['password'];
      $confirm = $_POST['confirm_password'];
      
      $menu_masters = isset($_POST['menu_masters']) ? 1 : 0;
      $menu_transaction = isset($_POST['menu_transaction']) ? 1 : 0;
      $menu_payments = isset($_POST['menu_payments']) ? 1 : 0;
      $menu_reports = isset($_POST['menu_reports']) ? 1 : 0;

      $sub_perms_string = '';
      if(isset($_POST['sub_perms']) && !empty($_POST['sub_perms'])){
          $clean_perms = array_map(function($p) use ($db) {
              return remove_junk($db->escape(trim($p)));
          }, $_POST['sub_perms']);
          $sub_perms_string = implode(',', $clean_perms);
      }

      $sql = "UPDATE users SET 
          name='{$name}', username='{$username}', user_level={$level}, center_id={$center_id},
          menu_masters={$menu_masters}, menu_transaction={$menu_transaction},
          menu_payments={$menu_payments}, menu_reports={$menu_reports}, sub_permissions='{$sub_perms_string}'";

      if(!empty($password)){
          if($password != $confirm){
              $session->msg('d',"Password not matched");
              redirect('users.php?edit='.$id);
          }
          $sql .= ", password='{$password}'";
      }

      $sql .= " WHERE id={$id}";

      if($db->query($sql)){
          $session->msg('s',"User updated successfully");
      }else{
          $session->msg('d',"User update failed");
      }
      redirect('users.php');
  }

  /* ================= FETCH DATA FOR VIEW ================= */
  $groups = find_all('user_groups');
  $centers = find_by_sql("SELECT center_id, center_name FROM master_center WHERE org_id={$org_id} ORDER BY center_name ASC");
  $all_users = find_all_user();
  
  $edit_user = null;
  if(isset($_GET['edit'])){
      $edit_user = find_by_id('users', (int)$_GET['edit']);
  }

  $saved_perms = [];
  if(isset($edit_user) && !empty($edit_user['sub_permissions'])) {
      $saved_perms = array_map('trim', explode(',', $edit_user['sub_permissions']));
  }
?>

<?php include_once('layouts/header.php'); ?>

<style>
body { background-color: #f4f7fb; }
.panel { border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 15px; background: #ffffff; }
.panel-heading { padding: 10px 15px; font-size: 13px; font-weight: 700; border-bottom: 2px solid #2b8cff !important; background: #ffffff !important; color: #1d3557; border-top-left-radius: 8px; border-top-right-radius: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.panel-body { padding: 15px; }

.table-scrollable { max-height: 500px; overflow-y: auto; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 6px; }
.table { margin-bottom: 0; font-size: 12px; width: 100%; }
.table thead { position: sticky; top: 0; background: #18233b; color: #ffffff; z-index: 10; }
.table thead th { border: none !important; padding: 8px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; background: #18233b; }
.table tbody td { padding: 8px 10px; vertical-align: middle !important; border-color: #f1f5f9; }
.table tbody tr:hover { background: #f8fafc; }
.search-box { position: relative; width: 100%; margin-bottom: 12px; }
.search-box i { position: absolute; left: 10px; top: 9px; color: #94a3b8; font-size: 12px; }
.search-box input { padding-left: 30px; height: 32px !important; font-size: 12px; border-radius: 5px; border: 1px solid #cbd5e1; }
.badge-active { background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; }
.badge-deactive { background: #fef2f2; color: #991b1b; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; }
.action-td { width: 80px; text-align: center; white-space: nowrap !important; }
.action-cell { display: flex !important; flex-direction: row !important; align-items: center !important; justify-content: center !important; gap: 6px !important; }
.equal-btn { width: 28px !important; height: 26px !important; padding: 0 !important; line-height: 26px !important; font-size: 11px !important; border-radius: 4px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; margin: 0 !important; }

.perm-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 15px; margin-bottom: 12px; background: #f8fafc; }
.perm-title { margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between; }
.perm-title label { margin: 0; font-size: 13px; color: #1e293b; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;}
.sub-perms { display: none; margin-left: 24px; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #cbd5e1; }
.sub-perms-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; font-size: 11px; }
.sub-perms-grid label { font-weight: 500; color: #475569; margin: 0; display: flex; align-items: center; gap: 5px; cursor: pointer;}
.select-all-box { margin-bottom: 15px; padding: 8px 12px; background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 6px; }
.select-all-sub { font-size: 11px; color: #0284c7; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px; }
</style>

<div class="row">
    <div class="col-md-4">
        <div class="panel panel-primary">
            <div class="panel-heading" style="background:#2b8cff; color:white; border:none;">
                <strong>
                    <span class="glyphicon glyphicon-th"></span>
                    <?php echo isset($edit_user) ? 'Edit User' : 'Create User'; ?>
                </strong>
            </div>

            <div class="panel-body">
                <form method="post" action="users.php<?php echo isset($_GET['edit']) ? '?edit='.(int)$_GET['edit'] : ''; ?>">
                    
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" name="full-name" value="<?php echo isset($edit_user['name']) ? remove_junk($edit_user['name']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" class="form-control" name="username" value="<?php echo isset($edit_user['username']) ? remove_junk($edit_user['username']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="password" value="<?php echo isset($edit_user['password']) ? $edit_user['password'] : ''; ?>" placeholder="Leave blank if not changing">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" onclick="togglePassword()">👁</button>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" value="<?php echo isset($edit_user['password']) ? $edit_user['password'] : ''; ?>">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" onclick="toggleConfirmPassword()">👁</button>
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Center</label>
                        <select class="form-control" name="center_id" required>
                            <option value="">Select Center</option>
                            <?php foreach($centers as $center): ?>
                                <option value="<?php echo (int)$center['center_id']; ?>" <?php if(isset($edit_user) && $center['center_id'] == $edit_user['center_id']) echo 'selected'; ?>>
                                    <?php echo remove_junk($center['center_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>User Role</label>
                        <select class="form-control" name="level">
                            <?php foreach ($groups as $group ): ?>
                                <option value="<?php echo (int)$group['group_level']; ?>" <?php if(isset($edit_user) && $group['group_level']==$edit_user['user_level']) echo 'selected'; ?>>
                                    <?php echo ucwords(remove_junk($group['group_name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label style="color:#2b8cff; font-weight:bold; font-size: 13px; text-transform: uppercase; margin-bottom: 10px;">Module Permissions</label>
                        
                        <div class="select-all-box">
                            <label style="margin: 0; font-size: 13px; color: #0369a1; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="chk_all" onchange="toggleAll(this)"> 
                                SELECT ALL PERMISSIONS
                            </label>
                        </div>

                        <!-- MASTERS -->
                        <div class="perm-box">
                            <div class="perm-title">
                                <label>
                                    <input type="checkbox" name="menu_masters" id="chk_masters" value="1" onchange="toggleSub('masters')" <?php if(isset($edit_user) && $edit_user['menu_masters'] == 1) echo 'checked'; ?>> 
                                    MASTERS
                                </label>
                                <label class="select-all-sub">
                                    <input type="checkbox" id="select_all_masters" onchange="toggleSubModuleAll('masters', this)"> Select All
                                </label>
                            </div>
                            <div class="sub-perms" id="sub_masters" style="<?php echo (isset($edit_user) && $edit_user['menu_masters'] == 1) ? 'display:block;' : ''; ?>">
                                <div class="sub-perms-grid">
                                    <label><input type="checkbox" name="sub_perms[]" value="org_master" <?php if(in_array('org_master', $saved_perms)) echo 'checked'; ?>> Org Master</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="centers" <?php if(in_array('centers', $saved_perms)) echo 'checked'; ?>> Centers</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="paymode" <?php if(in_array('paymode', $saved_perms)) echo 'checked'; ?>> Paymode</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="supplier_master" <?php if(in_array('supplier_master', $saved_perms)) echo 'checked'; ?>> Supplier Master</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="customer_master" <?php if(in_array('customer_master', $saved_perms)) echo 'checked'; ?>> Customer Master</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="products" <?php if(in_array('products', $saved_perms)) echo 'checked'; ?>> Products</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="bom_master" <?php if(in_array('bom_master', $saved_perms)) echo 'checked'; ?>> BOM Master</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="user_role" <?php if(in_array('user_role', $saved_perms)) echo 'checked'; ?>> User Role</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="users" <?php if(in_array('users', $saved_perms)) echo 'checked'; ?>> Users</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="categories" <?php if(in_array('categories', $saved_perms)) echo 'checked'; ?>> Categories</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="gst_master" <?php if(in_array('gst_master', $saved_perms)) echo 'checked'; ?>> GST Master</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="gst_state" <?php if(in_array('gst_state', $saved_perms)) echo 'checked'; ?>> GST State Code</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="shipping_type" <?php if(in_array('shipping_type', $saved_perms)) echo 'checked'; ?>> Shipping Type</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="config_master" <?php if(in_array('config_master', $saved_perms)) echo 'checked'; ?>> Config Master</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="rate_master" <?php if(in_array('rate_master', $saved_perms)) echo 'checked'; ?>> Rate Master</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="fin_year" <?php if(in_array('fin_year', $saved_perms)) echo 'checked'; ?>> Financial Year</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="sequence_master" <?php if(in_array('sequence_master', $saved_perms)) echo 'checked'; ?>> Sequence Master</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="bank_master" <?php if(in_array('bank_master', $saved_perms)) echo 'checked'; ?>> Bank Master</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="print_type" <?php if(in_array('print_type', $saved_perms)) echo 'checked'; ?>> Print Type</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="terms_cond" <?php if(in_array('terms_cond', $saved_perms)) echo 'checked'; ?>> Terms Conditions</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="expense_master" <?php if(in_array('expense_master', $saved_perms)) echo 'checked'; ?>> Expense Master</label>
                                </div>
                            </div>
                        </div>

                        <!-- TRANSACTIONS -->
                        <div class="perm-box">
                            <div class="perm-title">
                                <label>
                                    <input type="checkbox" name="menu_transaction" id="chk_transactions" value="1" onchange="toggleSub('transactions')" <?php if(isset($edit_user) && $edit_user['menu_transaction'] == 1) echo 'checked'; ?>> 
                                    TRANSACTIONS
                                </label>
                                <label class="select-all-sub">
                                    <input type="checkbox" id="select_all_transactions" onchange="toggleSubModuleAll('transactions', this)"> Select All
                                </label>
                            </div>
                            <div class="sub-perms" id="sub_transactions" style="<?php echo (isset($edit_user) && $edit_user['menu_transaction'] == 1) ? 'display:block;' : ''; ?>">
                                <div class="sub-perms-grid">
                                    <label><input type="checkbox" name="sub_perms[]" value="grn" <?php if(in_array('grn', $saved_perms)) echo 'checked'; ?>> GRN</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="quotation" <?php if(in_array('quotation', $saved_perms)) echo 'checked'; ?>> Quotation</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="demo_item" <?php if(in_array('demo_item', $saved_perms)) echo 'checked'; ?>> Demo Item Detail</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="invoice" <?php if(in_array('invoice', $saved_perms)) echo 'checked'; ?>> Invoice</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="manufacture" <?php if(in_array('manufacture', $saved_perms)) echo 'checked'; ?>> Manufacture</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="return" <?php if(in_array('return', $saved_perms)) echo 'checked'; ?>> Return</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="direct_billing" <?php if(in_array('direct_billing', $saved_perms)) echo 'checked'; ?>> Direct Billing</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="duplicate_print" <?php if(in_array('duplicate_print', $saved_perms)) echo 'checked'; ?>> Duplicate Print</label>
                                </div>
                            </div>
                        </div>

                        <!-- PAYMENTS -->
                        <div class="perm-box">
                            <div class="perm-title">
                                <label>
                                    <input type="checkbox" name="menu_payments" id="chk_payments" value="1" onchange="toggleSub('payments')" <?php if(isset($edit_user) && $edit_user['menu_payments'] == 1) echo 'checked'; ?>> 
                                    PAYMENTS
                                </label>
                                <label class="select-all-sub">
                                    <input type="checkbox" id="select_all_payments" onchange="toggleSubModuleAll('payments', this)"> Select All
                                </label>
                            </div>
                            <div class="sub-perms" id="sub_payments" style="<?php echo (isset($edit_user) && $edit_user['menu_payments'] == 1) ? 'display:block;' : ''; ?>">
                                <div class="sub-perms-grid">
                                    <label><input type="checkbox" name="sub_perms[]" value="supp_advance" <?php if(in_array('supp_advance', $saved_perms)) echo 'checked'; ?>> Supplier Advance</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="pay_pendency" <?php if(in_array('pay_pendency', $saved_perms)) echo 'checked'; ?>> Payment Pendency</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="expense" <?php if(in_array('expense', $saved_perms)) echo 'checked'; ?>> Expense</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="manage_payments" <?php if(in_array('manage_payments', $saved_perms)) echo 'checked'; ?>> Manage Payments</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="payment_report" <?php if(in_array('payment_report', $saved_perms)) echo 'checked'; ?>> Payments Report</label>
                                </div>
                            </div>
                        </div>

                        <!-- REPORTS -->
                        <div class="perm-box">
                            <div class="perm-title">
                                <label>
                                    <input type="checkbox" name="menu_reports" id="chk_reports" value="1" onchange="toggleSub('reports')" <?php if(isset($edit_user) && $edit_user['menu_reports'] == 1) echo 'checked'; ?>> 
                                    REPORTS
                                </label>
                                <label class="select-all-sub">
                                    <input type="checkbox" id="select_all_reports" onchange="toggleSubModuleAll('reports', this)"> Select All
                                </label>
                            </div>
                            <div class="sub-perms" id="sub_reports" style="<?php echo (isset($edit_user) && $edit_user['menu_reports'] == 1) ? 'display:block;' : ''; ?>">
                                <div class="sub-perms-grid">
                                    <label><input type="checkbox" name="sub_perms[]" value="stock_report" <?php if(in_array('stock_report', $saved_perms)) echo 'checked'; ?>> Stock Report</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="sales_report" <?php if(in_array('sales_report', $saved_perms)) echo 'checked'; ?>> Sales Report (Inv)</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="purchase_report" <?php if(in_array('purchase_report', $saved_perms)) echo 'checked'; ?>> Purchase Report</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="ledger_report" <?php if(in_array('ledger_report', $saved_perms)) echo 'checked'; ?>> Ledger Report</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="revenue_report" <?php if(in_array('revenue_report', $saved_perms)) echo 'checked'; ?>> Revenue Report</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="expense_report" <?php if(in_array('expense_report', $saved_perms)) echo 'checked'; ?>> Expense Report</label>
                                    <label><input type="checkbox" name="sub_perms[]" value="business_report" <?php if(in_array('business_report', $saved_perms)) echo 'checked'; ?>> Sales Report (Bill)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group clearfix">
                        <?php if(isset($edit_user)): ?>
                            <a href="users.php" class="btn btn-default pull-left">Cancel</a>
                            <button type="submit" name="update_user" class="btn btn-warning pull-right">Update User</button>
                        <?php else: ?>
                            <button type="submit" name="add_user" class="btn btn-primary pull-right">Add User</button>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- USERS LIST -->
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-user" style="color: #2b8cff; margin-right: 5px;"></i>
                <strong>USERS LIST</strong>
            </div>

            <div class="panel-body">
                <div class="search-box">
                    <i class="glyphicon glyphicon-search"></i>
                    <input type="text" id="userSearch" class="form-control" placeholder="Search user...">
                </div>

                <div class="table-scrollable">
                    <table class="table table-bordered table-striped" id="usersTable">
                        <thead>
                            <tr>
                                <th class="text-center" width="40">#</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Center</th>
                                <th class="text-center">User Role</th>
                                <th class="text-center">Status</th>
                                <th class="text-center" width="80">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($all_users)): ?>
                                <?php foreach($all_users as $i => $a_user): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $i + 1;?></td>
                                        <td><strong><?php echo remove_junk(ucwords($a_user['name']))?></strong></td>
                                        <td><?php echo remove_junk($a_user['username'])?></td>
                                        <td><?php echo remove_junk($a_user['center_name'] ?? ''); ?></td>
                                        <td class="text-center"><?php echo remove_junk(ucwords($a_user['group_name']))?></td>
                                        <td class="text-center">
                                            <?php if($a_user['status'] === '1'): ?>
                                                <span class="badge-active">Active</span>
                                            <?php else: ?>
                                                <span class="badge-deactive">Deactive</span>
                                            <?php endif;?>
                                        </td>
                                        <td class="action-td">
                                            <div class="action-cell">
                                                <a href="users.php?edit=<?php echo (int)$a_user['id'];?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                                    <i class="glyphicon glyphicon-pencil"></i>
                                                </a>
                                                <button type="button" onclick="confirmDelete(<?php echo (int)$a_user['id'];?>)" class="btn btn-danger btn-xs equal-btn" title="Remove">
                                                    <i class="glyphicon glyphicon-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach;?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center" style="color: #94a3b8; padding: 15px;">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function togglePassword(){
    let pass=document.getElementById("password");
    pass.type = pass.type === "password" ? "text":"password";
}

function toggleConfirmPassword(){
    let pass=document.getElementById("confirm_password");
    pass.type = pass.type === "password" ? "text":"password";
}

function toggleSub(module) {
    let mainChk = document.getElementById('chk_' + module);
    let subDiv = document.getElementById('sub_' + module);
    
    if (mainChk.checked) {
        let modules = ['masters', 'transactions', 'payments', 'reports'];
        modules.forEach(function(mod) {
            if (mod !== module) {
                let otherChk = document.getElementById('chk_' + mod);
                let otherDiv = document.getElementById('sub_' + mod);
                let otherSelectAll = document.getElementById('select_all_' + mod);
                if (otherChk) otherChk.checked = false;
                if (otherSelectAll) otherSelectAll.checked = false;
                if (otherDiv) {
                    otherDiv.style.display = 'none';
                    let checkboxes = otherDiv.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(function(chk) { chk.checked = false; });
                }
            }
        });
        subDiv.style.display = 'block';
    } else {
        subDiv.style.display = 'none';
        let subAllChk = document.getElementById('select_all_' + module);
        if (subAllChk) subAllChk.checked = false;
        let checkboxes = subDiv.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(function(chk) { chk.checked = false; });
    }
}

function toggleSubModuleAll(module, source) {
    let isChecked = source.checked;
    let mainChk = document.getElementById('chk_' + module);
    let subDiv = document.getElementById('sub_' + module);

    if (isChecked && mainChk) {
        mainChk.checked = true;
        subDiv.style.display = 'block';
    }

    if (subDiv) {
        let checkboxes = subDiv.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(function(chk) { chk.checked = isChecked; });
    }
}

function toggleAll(source) {
    let isChecked = source.checked;
    let modules = ['masters', 'transactions', 'payments', 'reports'];
    
    modules.forEach(function(mod) {
        let mainChk = document.getElementById('chk_' + mod);
        let subDiv = document.getElementById('sub_' + mod);
        let subAllChk = document.getElementById('select_all_' + mod);
        
        if (mainChk) mainChk.checked = isChecked;
        if (subAllChk) subAllChk.checked = isChecked;
        
        if (subDiv) {
            subDiv.style.display = isChecked ? 'block' : 'none';
            let subCheckboxes = subDiv.querySelectorAll('input[type="checkbox"]');
            subCheckboxes.forEach(function(chk) { chk.checked = isChecked; });
        }
    });
}

document.getElementById("userSearch").addEventListener("keyup", function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#usersTable tbody tr");

    rows.forEach(function(row){
        let text = row.textContent.toLowerCase();
        row.style.display = (text.indexOf(filter) > -1) ? "" : "none";
    });
});

function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This user will be deleted permanently!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "delete_user.php?id=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
