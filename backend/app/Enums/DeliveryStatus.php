<?php

namespace App\Enums;

// How far a confirmed order of products has got on its way to the customer
enum DeliveryStatus: string
{
    case OnTheWay = 'on_the_way';
    case Delivered = 'delivered';
}
