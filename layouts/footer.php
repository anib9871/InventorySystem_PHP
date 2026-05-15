     </div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.min.js"></script>
  <script type="text/javascript" src="libs/js/functions.js"></script>

  <!-- ✅ SIDEBAR TOGGLE SCRIPT -->
<script>
$(document).ready(function(){

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

        // ✅ CLOSE ONLY SAME LEVEL MENUS
        currentToggle
            .parent()
            .siblings()
            .find("> .submenu")
            .slideUp(200);

        currentToggle
            .parent()
            .siblings()
            .find("> a .arrow")
            .removeClass("rotate");

        // ✅ REMOVE STORAGE OF CLOSED MENUS
        currentToggle
            .parent()
            .siblings()
            .find("> a.submenu-toggle")
            .each(function(){

                let siblingKey = $(this).attr("data-menu");
                localStorage.removeItem(siblingKey);

            });

        // ✅ TOGGLE CURRENT MENU
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
</body>
</html>

<?php if(isset($db)) { $db->db_disconnect(); } ?>
