const orderForm=document.getElementById("orderForm");
if(orderForm){
    const customerSelect=document.getElementById("customer_id");
    const customerFields={
        name:document.getElementById("customer_name"),
        phone:document.getElementById("customer_phone"),
        address:document.getElementById("shipping_address")
    };
    const updateCustomerFields=function(){
        const option=customerSelect.selectedOptions[0];
        const hasSavedCustomer=Boolean(option.value);
        customerFields.name.value=hasSavedCustomer?option.dataset.name:"";
        customerFields.phone.value=hasSavedCustomer?option.dataset.phone:"";
        customerFields.address.value=hasSavedCustomer?option.dataset.address:"";
        Object.values(customerFields).forEach(function(field){field.readOnly=hasSavedCustomer;});
    };
    customerSelect.addEventListener("change",updateCustomerFields);
    if(customerSelect.value) updateCustomerFields();

    const itemList=document.getElementById("orderItems");
    const itemTemplate=document.getElementById("orderItemTemplate");
    const addButton=document.getElementById("addOrderItem");
    let nextIndex=itemList.querySelectorAll("[data-order-item]").length;

    addButton.addEventListener("click",function(){
        const row=itemTemplate.content.firstElementChild.cloneNode(true);
        row.querySelectorAll("[name]").forEach(function(field){
            field.name=field.name.replaceAll("__INDEX__",String(nextIndex));
        });
        nextIndex+=1;
        itemList.appendChild(row);
    });

    itemList.addEventListener("click",function(event){
        const button=event.target.closest(".remove-order-item");
        if(button){
            button.closest("[data-order-item]").remove();
        }
    });
}
