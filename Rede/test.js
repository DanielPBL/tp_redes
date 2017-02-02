
var rts = require('./rede.js');

var rota = new rts.Rota('192.168.1.0', '255.255.255.0', '192.168.1.10:6010/6001/5002');
var tabl = new rts.Tabela(testTabl, './tabela_c.txt');

console.log(rota);
console.log(rota.match('192.168.1.1'));
console.log(rota.match('192.168.1.255'));
console.log(rota.match('192.168.2.1'));
console.log(rota.match('192.168.2.255'));

function testTabl() {
    console.log(tabl.match('192.168.1.1'));
    console.log(tabl.match('192.168.1.255'));
    console.log(tabl.match('192.168.2.1'));
    console.log(tabl.match('192.168.2.255'));
}
