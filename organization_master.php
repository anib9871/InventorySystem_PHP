<?php
$page_title = 'Organization Master';
require_once('includes/load.php');
//page_require_level(2);

/* FETCH ORGANIZATION LIST */
$org_id = $_SESSION['org_id'];

$orgs = find_by_sql("SELECT om.*, gsm.state_name 
FROM organization_master om
LEFT JOIN gst_state_master gsm ON gsm.id = om.state_id
WHERE om.id = '{$org_id}'
ORDER BY om.id DESC");

/* FETCH STATE LIST */
$states = find_by_sql("SELECT * FROM gst_state_master ORDER BY state_name ASC");

/* ADD ORGANIZATION */
if(isset($_POST['add_org'])){
  $mnemonic = strtoupper(substr(remove_junk($db->escape($_POST['mnemonic'])),0,5));
  
  $address_array = [];
  if (isset($_POST['addr_text']) && is_array($_POST['addr_text'])) {
      for ($i = 0; $i < count($_POST['addr_text']); $i++) {
          $txt = remove_junk($db->escape($_POST['addr_text'][$i]));
          $s_name = isset($_POST['addr_state_name'][$i]) ? remove_junk($db->escape($_POST['addr_state_name'][$i])) : '';
          $s_code = isset($_POST['addr_state_code'][$i]) ? remove_junk($db->escape($_POST['addr_state_code'][$i])) : '';
          
          if (!empty($txt)) {
              $address_array[] = ['text' => $txt, 'state_name' => $s_name, 'state_code' => $s_code];
          }
      }
  }
  $address = json_encode($address_array);

  $phone    = remove_junk($db->escape($_POST['phone']));
  $email    = remove_junk($db->escape($_POST['email']));
  $contact  = remove_junk($db->escape($_POST['contact']));
  $gst      = remove_junk($db->escape($_POST['gst']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));

  if(empty($mnemonic)){
      $session->msg("d", "Mnemonic field khali nahi ho sakta!");
      redirect('organization_master.php', false);
  }

  $master_org_id = $_SESSION['org_id'];
  $master_org = find_by_sql("SELECT org_id, org_name FROM master_inventory.master_organization WHERE org_id = '{$master_org_id}' LIMIT 1");

  if(!$master_org){
      $session->msg("d", "Invalid Master Organization found!");
      redirect('organization_master.php', false);
  }

  $org_id   = $master_org[0]['org_id'];
  $org_name = $master_org[0]['org_name'];

  $sql = "INSERT INTO organization_master
  (id,mnemonic,org_name,address,state_id,state_code,phone,email,contact_person,gst_no)
  VALUES
  ('$org_id','$mnemonic','$org_name','$address','$state_id','$state_code',
   '$phone','$email','$contact','$gst')";

  if($db->query($sql)){
      $session->msg("s","Organization added successfully!");
  } else {
      $session->msg("d","Failed to add organization to database!");
  }
  redirect('organization_master.php',false);
}

/* UPDATE ORGANIZATION */
if(isset($_POST['update_org'])){
  $id = (int)$_POST['id'];
  $mnemonic = strtoupper(substr(remove_junk($db->escape($_POST['mnemonic'])),0,5));
  $master_org_id = $_SESSION['org_id'];

  if(empty($mnemonic)){
      $session->msg("d", "Mnemonic field khali nahi ho sakta!");
      redirect('organization_master.php', false);
  }

  $master_org = find_by_sql("SELECT org_name FROM master_inventory.master_organization WHERE org_id = '{$master_org_id}' LIMIT 1");
  if(!$master_org){
      $session->msg("d", "Master Organization record missing!");
      redirect('organization_master.php', false);
  }
  $org_name = $master_org[0]['org_name'];

  $address_array = [];
  if (isset($_POST['addr_text']) && is_array($_POST['addr_text'])) {
      for ($i = 0; $i < count($_POST['addr_text']); $i++) {
          $txt = remove_junk($db->escape($_POST['addr_text'][$i]));
          $s_name = isset($_POST['addr_state_name'][$i]) ? remove_junk($db->escape($_POST['addr_state_name'][$i])) : '';
          $s_code = isset($_POST['addr_state_code'][$i]) ? remove_junk($db->escape($_POST['addr_state_code'][$i])) : '';
          
          if (!empty($txt)) {
              $address_array[] = ['text' => $txt, 'state_name' => $s_name, 'state_code' => $s_code];
          }
      }
  }
  $address = json_encode($address_array);

  $phone    = remove_junk($db->escape($_POST['phone']));
  $email    = remove_junk($db->escape($_POST['email']));
  $contact  = remove_junk($db->escape($_POST['contact']));
  $gst      = remove_junk($db->escape($_POST['gst']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));

  $sql = "UPDATE organization_master SET
            mnemonic='$mnemonic',
            org_name='$org_name',
            address='$address',
            state_id='$state_id',
            state_code='$state_code',
            phone='$phone',
            email='$email',
            contact_person='$contact',
            gst_no='$gst'
        WHERE id='{$_SESSION['org_id']}'";

  if($db->query($sql)){
      $session->msg("s","Organization updated successfully!");
  } else {
      $session->msg("d","Update query failed!");
  }
  redirect('organization_master.php',false);
}

/* EDIT LOAD */
$edit = false;
if(isset($_GET['edit'])){
   $eid = (int)$_GET['edit'];
   $edit = find_by_id("organization_master",$eid);
   if(!$edit){
       $session->msg("d", "Record not found!");
       redirect('organization_master.php', false);
   }
}

/* DELETE */
if(isset($_GET['del'])){
   $id = (int)$_GET['del'];
   if($db->query("DELETE FROM organization_master WHERE id='$id'")){
       $session->msg("s","Organization deleted successfully!");
   } else {
       $session->msg("d","Failed to delete organization!");
   }
   redirect('organization_master.php',false);
}

include_once('layouts/header.php');
?>

<div class="row">
    <!-- ADD/EDIT FORM -->
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading org-title">
                <i class="glyphicon glyphicon-briefcase"></i> ORGANIZATION MASTER
            </div>
            <div class="panel-body">
                <form method="post" id="orgForm">
                    <?php if($edit){ ?>
                        <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
                    <?php } ?>

                    <?php
                    $master_org = find_by_sql("
                    SELECT org_name
                    FROM master_inventory.master_organization
                    WHERE org_id = '{$_SESSION['org_id']}'
                    LIMIT 1
                    ");
                    $org_name = $master_org ? $master_org[0]['org_name'] : '';
                    ?>

                    <!-- Row 1 -->
                    <div class="row">
                        <div class="col-md-2 form-group-compact">
                            <label>Mnemonic</label>
                            <input type="text" maxlength="5" name="mnemonic" class="form-control" value="<?php echo $edit ? $edit['mnemonic'] : ''; ?>" required placeholder="e.g. FERT">
                        </div>

                        <div class="col-md-3 form-group-compact">
                            <label>Organization</label>
                            <input type="text" class="form-control" value="<?php echo $edit ? $edit['org_name'] : $org_name; ?>" readonly>
                        </div>

                        <div class="col-md-3 form-group-compact">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo $edit ? $edit['phone'] : ''; ?>" placeholder="Phone Number">
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo $edit ? $edit['email'] : ''; ?>" placeholder="Email Address">
                        </div>
                    </div>

                    <!-- Row 2 -->
                    <div class="row">
                        <div class="col-md-3 form-group-compact">
                            <label>Contact Person</label>
                            <input type="text" name="contact" class="form-control" value="<?php echo $edit ? $edit['contact_person'] : ''; ?>" placeholder="Person Name">
                        </div>

                        <div class="col-md-3 form-group-compact">
                            <label>State</label>
                            <select name="state_id" id="state_id" class="form-control">
                                <option value="">Select State</option>
                                <?php foreach($states as $s){ ?>
                                    <option value="<?php echo $s['id'];?>" data-code="<?php echo $s['state_code'];?>" <?php if($edit && $edit['state_id']==$s['id']) echo "selected"; ?>>
                                        <?php echo $s['state_name'];?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-2 form-group-compact">
                            <label>State Code</label>
                            <input type="text" id="state_code" name="state_code" class="form-control" readonly value="<?php echo $edit ? $edit['state_code'] : ''; ?>">
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>GST No</label>
                            <input type="text" name="gst" class="form-control" value="<?php echo $edit ? $edit['gst_no'] : ''; ?>" placeholder="22AAAAA0000A1Z5">
                        </div>
                    </div>

                    <!-- Row 3 -->
                    <div class="row">
                        <div class="col-md-8 form-group-compact" id="address-container">
                            <label>Address (With Specific State & Code) <button type="button" class="btn btn-xs btn-info" onclick="addAddressField()" style="margin-left: 10px; padding: 2px 6px; background-color: #00bcd4; border: none; color: white;">+ Add More</button></label>
                            
                            <?php 
                            $saved_addresses = [];
                            if($edit && !empty($edit['address'])){
                                $decoded = json_decode($edit['address'], true);
                                if(is_array($decoded) && count($decoded) > 0){
                                    foreach($decoded as $d){
                                        if(is_array($d)){
                                            $saved_addresses[] = $d;
                                        } else if (is_string($d)) {
                                            $saved_addresses[] = ['text' => $d, 'state_name' => '', 'state_code' => ''];
                                        }
                                    }
                                }
                            }
                            if(empty($saved_addresses)){
                                $saved_addresses[] = ['text' => '', 'state_name' => '', 'state_code' => ''];
                            }
                            ?>

                            <?php foreach($saved_addresses as $index => $addr): ?>
                            <div class="input-group" style="margin-bottom: 5px; width: 100%; display: flex; gap: 5px;">
                                <textarea name="addr_text[]" rows="1" class="form-control" style="width: 45%;" placeholder="Complete address..."><?php echo isset($addr['text']) ? $addr['text'] : ''; ?></textarea>
                                
                                <select name="addr_state_name[]" class="form-control" style="width: 35%;" onchange="this.nextElementSibling.value = this.options[this.selectedIndex].getAttribute('data-code') || ''">
                                    <option value="">Select State</option>
                                    <?php foreach($states as $s): ?>
                                        <option value="<?php echo $s['state_name']; ?>" data-code="<?php echo $s['state_code']; ?>" <?php if(isset($addr['state_name']) && $addr['state_name'] == $s['state_name']) echo 'selected'; ?>>
                                            <?php echo $s['state_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <input type="text" name="addr_state_code[]" class="form-control" style="width: 15%;" value="<?php echo isset($addr['state_code']) ? $addr['state_code'] : ''; ?>" placeholder="Code" readonly>
                                
                                <?php if($index > 0): ?>
                                <span class="input-group-btn" style="vertical-align: top;">
                                    <button class="btn btn-danger" type="button" onclick="this.parentElement.parentElement.remove()" style="height: 34px; padding: 6px 12px;">X</button>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="col-md-4 form-group-compact text-right" style="padding-top: 18px;">
                            <?php if(!$edit){ ?>
                                <button type="button" class="btn btn-clear btn-custom" onclick="document.getElementById('orgForm').reset()">
                                    Clear
                                </button>
                            <?php } ?>

                            <?php if($edit){ ?>
                                <button class="btn btn-success-custom btn-custom" name="update_org">
                                    Update Organization
                                </button>
                            <?php }else{ ?>
                                <button class="btn btn-success-custom btn-custom" name="add_org">
                                    Save Organization
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- LIST -->
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">ORGANIZATION LIST</div>

            <div class="panel-body">
                <div class="search-box">
                    <i class="glyphicon glyphicon-search"></i>
                    <input type="text" id="search" class="form-control" placeholder="Search organization...">
                </div>

                <div class="table-scrollable">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th>Mnemonic</th>
                                <th>Organization</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <?php if(isset($gst_enabled) && $gst_enabled == "Yes"): ?>
                                    <th>State</th>
                                    <th>State Code</th>
                                    <th>GST No</th>
                                <?php endif; ?>
                                <th width="80" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="organizationTable">
                            <?php foreach($orgs as $i=>$o): ?>
                            <tr>
                                <td><?php echo $i+1; ?></td>
                                <td><strong><?php echo $o['mnemonic']; ?></strong></td>
                                <td><?php echo $o['org_name']; ?></td>
                                <td><?php echo $o['phone']; ?></td>
                                <td><?php echo $o['email']; ?></td>
                                <td><?php echo $o['contact_person']; ?></td>
                                <td class="address-cell">
                                    <?php 
                                        $decoded_addrs = json_decode($o['address'], true);
                                        if(is_array($decoded_addrs)){
                                            $display_arr = [];
                                            foreach($decoded_addrs as $da) {
                                                if(is_array($da) && isset($da['text'])) {
                                                    $display_arr[] = $da['text']; 
                                                } elseif (is_string($da)) {
                                                    $display_arr[] = $da; 
                                                }
                                            }
                                            echo implode('<hr style="margin:4px 0; border-color:#ddd;">', $display_arr);
                                        } else {
                                            echo $o['address']; 
                                        }
                                    ?>
                                </td>
                                <?php if(isset($gst_enabled) && $gst_enabled == "Yes"): ?>
                                    <td style="vertical-align: top;">
                                        <?php 
                                        if(is_array($decoded_addrs)){
                                            $state_arr = [];
                                            foreach($decoded_addrs as $idx => $da) {
                                                if(is_array($da) && !empty($da['state_name'])) {
                                                    $state_arr[] = "<span class='badge-state' style='display:inline-block; font-size:10px;'>{$da['state_name']}</span>";
                                                } elseif ($idx === 0 && !empty($o['state_name'])) {
                                                    $state_arr[] = "<span class='badge-state' style='display:inline-block; font-size:10px;'>{$o['state_name']}</span>";
                                                } else {
                                                    $state_arr[] = "<span style='visibility:hidden;'>-</span>"; 
                                                }
                                            }
                                            echo implode('<hr style="margin:4px 0; border-color:#ddd;">', $state_arr);
                                        } else {
                                            echo "<span class='badge-state'>{$o['state_name']}</span>";
                                        }
                                        ?>
                                    </td>
                                    <td style="vertical-align: top;">
                                        <?php 
                                        if(is_array($decoded_addrs)){
                                            $code_arr = [];
                                            foreach($decoded_addrs as $idx => $da) {
                                                if(is_array($da) && !empty($da['state_code'])) {
                                                    $code_arr[] = "<span style='font-weight:600;'>{$da['state_code']}</span>";
                                                } elseif ($idx === 0 && !empty($o['state_code'])) {
                                                    $code_arr[] = "<span style='font-weight:600;'>{$o['state_code']}</span>";
                                                } else {
                                                    $code_arr[] = "<span style='visibility:hidden;'>-</span>";
                                                }
                                            }
                                            echo implode('<hr style="margin:4px 0; border-color:#ddd;">', $code_arr);
                                        } else {
                                            echo $o['state_code'];
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $o['gst_no']; ?></td>
                                <?php endif; ?>
                                <td class="action-td">
                                    <div class="action-cell">
                                        <a href="organization_master.php?edit=<?php echo $o['id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </a>
                                        <button type="button" onclick="confirmDelete(<?php echo $o['id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById("state_id").addEventListener("change", function(){
    let code = this.options[this.selectedIndex].getAttribute("data-code") || '';
    document.getElementById("state_code").value = code;
});

document.getElementById("search").addEventListener("keyup", function(){
    let value = this.value.toLowerCase();
    document.querySelectorAll("#organizationTable tr").forEach(function(row){
        row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
    });
});

function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This organization will be deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "organization_master.php?del=" + id;
        }
    });
}

function addAddressField() {
    const container = document.getElementById('address-container');
    const div = document.createElement('div');
    div.className = 'input-group';
    div.style.cssText = 'margin-bottom: 5px; width: 100%; display: flex; gap: 5px;';

    let stateOptions = '<option value="">Select State</option>';
    <?php foreach($states as $s): ?>
    stateOptions += `<option value="<?php echo $s['state_name']; ?>" data-code="<?php echo $s['state_code']; ?>"><?php echo $s['state_name']; ?></option>`;
    <?php endforeach; ?>

    div.innerHTML = `
        <textarea name="addr_text[]" rows="1" class="form-control" style="width: 45%;" placeholder="Complete address..."></textarea>
        <select name="addr_state_name[]" class="form-control" style="width: 35%;" onchange="this.nextElementSibling.value = this.options[this.selectedIndex].getAttribute('data-code') || ''">
            ${stateOptions}
        </select>
        <input type="text" name="addr_state_code[]" class="form-control" style="width: 15%;" placeholder="Code" readonly>
        <span class="input-group-btn" style="vertical-align: top;">
            <button class="btn btn-danger" type="button" onclick="this.parentElement.parentElement.remove()" style="height: 34px; padding: 6px 12px;">X</button>
        </span>
    `;
    container.appendChild(div);
}
</script>

<?php include_once('layouts/footer.php'); ?>
