;(function(){
  const BASE_KEY='app.ui';
  let ns='default';
  let state={};
  const listeners=new Set();
  function k(){return BASE_KEY+':'+ns}
  function load(){
    try{
      const raw=localStorage.getItem(k());
      state=raw?JSON.parse(raw):{};
    }catch(e){state={}}
  }
  function save(){
    try{
      localStorage.setItem(k(),JSON.stringify(state));
    }catch(e){}
  }
  function splitPath(path){
    if(Array.isArray(path))return path;
    return String(path||'').split('.').filter(Boolean);
  }
  function get(path,def){
    const keys=splitPath(path);
    let cur=state;
    for(const key of keys){
      if(cur==null||!(key in cur))return def;
      cur=cur[key];
    }
    return cur===undefined?def:cur;
  }
  function set(path,value){
    const keys=splitPath(path);
    if(!keys.length)return;
    let cur=state;
    for(let i=0;i<keys.length-1;i++){
      const k=keys[i];
      if(typeof cur[k]!=='object'||cur[k]===null)cur[k]={};
      cur=cur[k];
    }
    cur[keys[keys.length-1]]=value;
    save();
    for(const fn of listeners)try{fn({type:'set',path,value})}catch(e){}
    return value;
  }
  function update(path,fn){
    const prev=get(path);
    const next=fn(prev);
    return set(path,next);
  }
  function merge(path,obj){
    const prev=get(path,{});
    const next=Object.assign({},prev,obj||{});
    return set(path,next);
  }
  function remove(path){
    const keys=splitPath(path);
    if(!keys.length)return;
    let cur=state;
    for(let i=0;i<keys.length-1;i++){
      const k=keys[i];
      if(typeof cur[k]!=='object'||cur[k]===null)return;
      cur=cur[k];
    }
    delete cur[keys[keys.length-1]];
    save();
    for(const fn of listeners)try{fn({type:'remove',path})}catch(e){}
  }
  function clear(){
    state={};
    save();
    for(const fn of listeners)try{fn({type:'clear'})}catch(e){}
  }
  function subscribe(fn){
    listeners.add(fn);
    return ()=>listeners.delete(fn);
  }
  function setNamespace(s){
    ns=String(s||'default');
    load();
    for(const fn of listeners)try{fn({type:'namespace',ns})}catch(e){}
  }
  function getState(){
    return JSON.parse(JSON.stringify(state));
  }
  load();
  window.UIStore={get,set,update,merge,remove,clear,subscribe,setNamespace,getState};
})(); 
