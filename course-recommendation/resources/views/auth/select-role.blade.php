<h2>Bạn là ai?</h2>

<form method="POST" action="{{ route('select-role') }}">
    @csrf
    <label><input type="radio" name="role" value="student"> Tôi là học viên</label><br>
    <label><input type="radio" name="role" value="instructor"> Tôi là giảng viên</label><br><br>

    <button type="submit">Tiếp tục</button>
</form>