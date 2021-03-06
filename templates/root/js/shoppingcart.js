function updateShoppingCarts(data) {
    var shoppingcartHolder = jQuery(".shoppingcartholder");
    shoppingcartHolder.each(function(){
        var html = data.html.slice(0);
        html = jQuery(html);
        if (!jQuery(this).hasClass('details')) {
            jQuery('[data-designed-to="details"]', html).remove();
        }
        jQuery(this).html(html);
    });
    jQuery(".spcnboitems").text(data.totalItems);
    if (data.totalItems > 0) {
        jQuery('.checkout-hidden-if-empty').removeClass("hidden");
    } else {
        jQuery('.checkout-hidden-if-empty').addClass("hidden");
    }
    handleProductCartBtns();
}

function addProductToCart (productId, variationId, amount) {
    var request2 = jQuery.ajax({
        url : window.location.protocol + '//'
            + window.location.host
            + '/blocks/'+jQuery('html').attr('lang')+'/shopping-cart/add-item-to-cart',
        type : "POST",
        data : {
            'current-page' : jQuery('body').attr('data-current-page'),
            'productId' : productId,
            'variationId': variationId,
            'amount':amount
        },
        dataType : "json"
    });

    request2.done(updateShoppingCarts);

    request2.fail(function(jqXHR, textStatus) {
        console.log("error in adding item to cart");
    });
}


function removeProductFromCart (productId, variationId, amount) {
    var request2 = jQuery.ajax({
        url : window.location.protocol + '//'
            + window.location.host
            + '/blocks/'+jQuery('html').attr('lang')+'/shopping-cart/remove-item-from-cart',
        type : "POST",
        data : {
            'current-page' : jQuery('body').attr('data-current-page'),
            'productId' : productId,
            'variationId': variationId,
            'amount':amount
        },
        dataType : "json"
    });

    request2.done(updateShoppingCarts);

    request2.fail(function(jqXHR, textStatus) {
        console.log("error in adding item to cart");
    });
}

jQuery(".productbuybtn").click(function(){
    var productId=jQuery(this).attr("data-productid");
    var variationId=jQuery(this).attr("data-variationid");
    var amount=1;
    if ((!jQuery.isEmptyObject(productId))&&(!jQuery.isEmptyObject(variationId))){
        jQuery(this).effect("transfer",{to:"#shoppingcart",className: "ui-effects-transfer"},500,function(){
            addProductToCart (productId, variationId, amount);
            jQuery("#shoppingcart").effect("bounce");
        });
    }
});

function handleProductCartBtns (){
    jQuery(".productremovelingnbtn").click(function(){
        var productId=jQuery(this).attr("data-productid");
        var variationId=jQuery(this).attr("data-variationid");
        var amount=jQuery(this).attr("data-amount");
        if ((!jQuery.isEmptyObject(productId))&&(!jQuery.isEmptyObject(variationId))&&(!jQuery.isEmptyObject(amount))){
            jQuery(this).parent().parent().effect("fade", {}, 200, function(){
                removeProductFromCart (productId, variationId, amount);
            });
        }
        return false;
    });
    jQuery(".productincreasebtn").click(function(){
        var productId=jQuery(this).attr("data-productid");
        var variationId=jQuery(this).attr("data-variationid");
        var amount=1;
        if ((!jQuery.isEmptyObject(productId))&&(!jQuery.isEmptyObject(variationId))){
            addProductToCart (productId, variationId, amount);
        }
        return false;
    });
    jQuery(".productdecreasebtn").click(function(){
        var productId=jQuery(this).attr("data-productid");
        var variationId=jQuery(this).attr("data-variationid");
        var amount=1;
        if ((!jQuery.isEmptyObject(productId))&&(!jQuery.isEmptyObject(variationId))){
            removeProductFromCart (productId, variationId, amount);
        }
        return false;
    });
}
handleProductCartBtns();