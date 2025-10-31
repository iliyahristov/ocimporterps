<div id="ocimp-progress" class="well" style="margin-top:15px; display:none;">
    <div class="progress">
        <div class="progress-bar progress-bar-success" role="progressbar" style="width:0%">0%</div>
    </div>
    <pre id="ocimp-log" style="max-height:240px; overflow:auto;"></pre>
</div>

{literal}
<script>
(function(){
  var running=false, offset=0, batch=0, entity='';
  function log(msg){
    var p=document.getElementById('ocimp-progress');
    var l=document.getElementById('ocimp-log');
    p.style.display='block';
    l.textContent += msg + "\\n";
    l.scrollTop = l.scrollHeight;
  }
  function tick(){
    if(!running) return;
    fetch('{/literal}{$ajax_url|escape:'htmlall':'UTF-8'}{literal}&entity='+encodeURIComponent(entity)+'&offset='+offset+'&ajax=1')
      .then(r=>r.json()).then(function(res){
        if(!res.ok){ throw new Error(res.error||'Unknown error'); }
        if(res.count>0){
          offset += res.count; batch += res.count; updateBar(); log('OK: '+res.count+' записи.'); tick();
        } else {
          running=false; updateBar(true); log('Готово.');
        }
      }).catch(function(e){ running=false; log('Грешка: '+e.message); });
  }
  function updateBar(done){
    var totals = {
      'category': {/literal}{$totals.category|intval}{literal},
      'product': {/literal}{$totals.product|intval}{literal},
      'customer': {/literal}{$totals.customer|intval}{literal},
      'order': {/literal}{$totals.order|intval}{literal}
    };
    var total = totals[entity]||0;
    var pc = total? Math.min(100, Math.round((offset/total)*100)) : 0;
    var bar = document.querySelector('#ocimp-progress .progress-bar');
    bar.style.width = pc+'%';
    bar.textContent = pc+'%';
    if(done){ bar.classList.add('progress-bar-striped'); }
  }
  document.querySelectorAll('.js-run').forEach(function(btn){
    btn.addEventListener('click', function(){
      if(running) return;
      entity = this.getAttribute('data-entity');
      running = true; offset = 0; batch = 0; 
      document.getElementById('ocimp-log').textContent='Старт '+entity+'...\\n';
      updateBar();
      tick();
    });
  });
})();
</script>
{/literal}
