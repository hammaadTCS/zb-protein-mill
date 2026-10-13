{{-- Spec §13.1 party statement (D-35). No cancelled entries, awaiting payments or notes. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Statement of Account</title>
    @include('pdf._styles')
</head>
<body>
    <footer>
        <table style="width: 100%;"><tr>
            <td>{{ $generated }}</td>
            <td class="num page"></td>
        </tr></table>
    </footer>

    <table class="head"><tr>
        <td style="width: 52px;"><img class="logo" src="{{ $logo }}" alt=""></td>
        <td class="company">{{ $company }}</td>
        <td class="title">Statement of Account</td>
    </tr></table>

    <table class="meta"><tr>
        <td>
            <strong>{{ $party['name'] }}</strong><br>
            <span class="muted">{{ $party['code'] }}@if ($party['mobile']) · {{ $party['mobile'] }}@endif</span>
        </td>
        <td class="num">
            <span class="muted">Period</span><br>
            {{ $from }} – {{ $to }}
        </td>
    </tr></table>

    <table class="rows">
        <thead>
            <tr>
                <th>Date</th>
                <th>Voucher</th>
                <th>Details</th>
                <th class="num">Increase</th>
                <th class="num">Decrease</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="strong">
                <td colspan="5">Opening balance</td>
                <td class="num">{{ $opening }}</td>
            </tr>
            @foreach ($rows as $row)
                <tr>
                    <td style="white-space: nowrap;">{{ $row['date'] }}</td>
                    <td style="white-space: nowrap;">{{ $row['voucher'] ?? '–' }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="num">{{ $row['increase'] ?? '' }}</td>
                    <td class="num">{{ $row['decrease'] ?? '' }}</td>
                    <td class="num">{{ $row['balance'] }}</td>
                </tr>
            @endforeach
            <tr class="strong">
                <td colspan="5">Closing balance</td>
                <td class="num">{{ $closing }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
