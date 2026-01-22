</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<script>
  const input = document.getElementById('idStudent');
  const datalist = document.getElementById('studentOptions');
  let t = null;

  input.addEventListener('input', () => {
    clearTimeout(t);
    const q = input.value.replace(/\D/g, '').trim();
    input.value = q;

    if (q.length < 1) {
      datalist.innerHTML = '';
      return;
    }

    t = setTimeout(async () => {
      try {
        const res = await fetch(`/academic/public/api/student-suggest?q=${encodeURIComponent(q)}`);
        const arr = await res.json();
        datalist.innerHTML = arr.map(v => `<option value="${v}"></option>`).join('');
      } catch (e) {
        datalist.innerHTML = '';
      }
    }, 200);
  });
</script>