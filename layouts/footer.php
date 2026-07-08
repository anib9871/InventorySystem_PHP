     </div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
  <script type="text/javascript" src="libs/js/functions.js"></script>

  <!-- ✅ SIDEBAR TOGGLE SCRIPT -->
<script>
$(document).ready(function(){

    // ✅ VERSION CHECK — naya deploy = localStorage saaf
    var SIDEBAR_VERSION = "v2"; // har deploy pe ye number badha
    if(localStorage.getItem('sidebar_ver') !== SIDEBAR_VERSION){
        // purani keys saaf karo
        ['inventory_main','billing_main','inventory_masters',
         'billing_masters','inventory_transaction','billing_transaction',
         'inventory_reports','billing_reports'].forEach(function(k){
            localStorage.removeItem(k);
        });
        localStorage.setItem('sidebar_ver', SIDEBAR_VERSION);
    }

    // RESTORE SAVED MENUS
    $(".submenu-toggle").each(function(){
        let menuKey = $(this).attr("data-menu");
        if(localStorage.getItem(menuKey) === "open"){
            $(this).next(".submenu").show();
            $(this).find(".arrow").addClass("rotate");
        }
    });

    // MENU TOGGLE
    $(".submenu-toggle").off("click").on("click", function(e){
        e.preventDefault();
        e.stopPropagation();

        let currentToggle = $(this);
        let submenu = currentToggle.next(".submenu");
        let arrow = currentToggle.find(".arrow");
        let menuKey = currentToggle.attr("data-menu");

        currentToggle.parent().siblings()
            .find("> .submenu").slideUp(200);
        currentToggle.parent().siblings()
            .find("> a .arrow").removeClass("rotate");
        currentToggle.parent().siblings()
            .find("> a.submenu-toggle").each(function(){
                localStorage.removeItem($(this).attr("data-menu"));
            });

        submenu.stop(true,true).slideToggle(200, function(){
            if(submenu.is(":visible")){
                localStorage.setItem(menuKey, "open");
                arrow.addClass("rotate");
            } else {
                localStorage.removeItem(menuKey);
                arrow.removeClass("rotate");
            }
        });
    });

});
</script>

<script>
$(document).ready(function(){

    $('#bill_date').datepicker({
        format: 'dd-mm-yyyy',
        autoclose: true,
        todayHighlight: true
    });

});
</script>

<script>

function deleteGRN(bill){

    Swal.fire({
        title: 'Delete GRN?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {

        if(result.isConfirmed){

            window.location =
            'delete_grn.php?bill=' + encodeURIComponent(bill);

        }

    });

}

</script>
</body>
</html>

<?php if(isset($db)) { $db->db_disconnect(); } ?>
