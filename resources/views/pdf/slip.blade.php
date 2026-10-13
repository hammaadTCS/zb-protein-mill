{{-- Spec §13.2 entry slip. "Our weight" is internal and never shown. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('pdf._styles')
</head>
<body>
    <footer>{{ $generated }}</footer>

    <table class="head"><tr>
        <td style="width: 52px;"><img class="logo" src="{{ $logo }}" alt=""></td>
        <td class="company">{{ $company }}</td>
        <td class="title">{{ $title }}</td>
    </tr></table>

    <table class="box">
        <tr><td class="label">Voucher number</td><td>{{ $voucher }}</td></tr>
        <tr><td class="label">Date</td><td>{{ $date }}</td></tr>
        <tr><td class="label">Party</td><td>{{ $party['name'] }} <span class="muted">({{ $party['code'] }})</span></td></tr>
        <tr><td class="label">Item</td><td>{{ $item }}</td></tr>
        <tr><td class="label">Kg</td><td>{{ $kg }}</td></tr>
        <tr><td class="label">Rate</td><td>{{ $rate }}</td></tr>
        <tr><td class="label">Amount</td><td>{{ $amount ?? '–' }}</td></tr>
        <tr><td class="label">Weight slip number</td><td>{{ $slip_no ?? '–' }}</td></tr>
        <tr><td class="label">{{ $money_now_label }}</td><td>{{ $money_now ?? '–' }}</td></tr>
    </table>

    <div class="balance">Balance: {{ $balance }}</div>
</body>
</html>
