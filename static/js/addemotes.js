/* DATA */
const emotes_list = [
	{src:"emo-yotsuba-angry.gif", value:":angry:"},
	{src:"emo-yotsuba-astonish.gif", value:":astonish:"},
	{src:"emo-yotsuba-biggrin.gif", value:":biggrin:"},
	{src:"emo-yotsuba-closed-eyes.gif", value:":closed-eyes:"},
	{src:"emo-yotsuba-closed-eyes2.gif", value:":closed-eyes2:"},
	{src:"emo-yotsuba-cool.gif", value:":cool:"},
	{src:"emo-yotsuba-cry.gif", value:":cry:"},
	{src:"emo-yotsuba-dark.gif", value:":dark:"},
	{src:"emo-yotsuba-dizzy.gif", value:":dizzy:"},
	{src:"emo-yotsuba-drool.gif", value:":drool:"},
	{src:"emo-yotsuba-glare.gif", value:":glare:"},
	{src:"emo-yotsuba-glare-01.gif", value:":glare1:"},
	{src:"emo-yotsuba-glare-02.gif", value:":glare2:"},
	{src:"emo-yotsuba-happy.gif", value:":happy:"},
	{src:"emo-yotsuba-huh.gif", value:":huh:"},
	{src:"emo-yotsuba-nosebleed.gif", value:":nosebleed:"},
	{src:"emo-yotsuba-nyaoo.gif", value:":nyaoo:"},
	{src:"emo-yotsuba-nyaoo2.gif", value:":nyaoo2:"},
	{src:"emo-yotsuba-nyaoo-closedeyes.gif", value:":nyaoo-closedeyes:"},
	{src:"emo-yotsuba-ph34r.gif", value:":ph34r:"},
	{src:"emo-yotsuba-rolleyes.gif", value:":rolleyes:"},
	{src:"emo-yotsuba-sad.gif", value:":sad:"},
	{src:"emo-yotsuba-smile.gif", value:":smile:"},
	{src:"emo-yotsuba-sweat.gif", value:":sweat:"},
	{src:"emo-yotsuba-sweat2.gif", value:":sweat2:"},
	{src:"emo-yotsuba-sweat3.gif", value:":sweat3:"},
	{src:"emo-yotsuba-tongue.gif", value:":tongue:"},
	{src:"emo-yotsuba-unsure.gif", value:":unsure:"},
	{src:"emo-yotsuba-wink.gif", value:":wink:"},
	{src:"emo-yotsuba-x3.gif", value:":x3:"},
	{src:"emo-yotsuba-xd.gif", value:":xd:"},
	{src:"emo-yotsuba-xp.gif", value:":xp:"},
	{src:"emo-yotsuba-heart.gif", value:":love:"},
	{src:"emo-yotsuba-blush3.gif", value:":blush:"},
	{src:"emo-yotsuba-mask.gif", value:":mask:"},
	{src:"emo.gif", value:":emo:"},
	{src:"emo-yotsuba-lolico.gif", value:":lolico:"},
	{src:"emo-yotsuba-tomo.gif", value:":kuz:"},
	{src:"heyuri-dance.gif", value:":dance:"},
	{src:"heyuri-dance-pantsu.gif", value:":dance2:"},
	{src:"nigra.gif", value:":nigra:"},
	{src:"sage.gif", value:":sage:"},
	{src:"longcat.gif", value:":longcat:"},
	{src:"tacgnol.gif", value:":tacgnol:"},
	{src:"mona2.gif", value:":mona2:"},
	{src:"nida.gif", value:":nida:"},
	{src:"iyahoo.gif", value:":iyahoo:"},
	{src:"banana.gif", value:":banana:"},
	{src:"onigiri.gif", value:":onigiri:"},
	{src:"anime_shii01.gif", value:":shii:"},
	{src:"anime_saitama05.gif", value:":saitama:"},
	{src:"foruda.gif", value:":foruda:"},
	{src:"nagato.gif", value:":nagato:"},
	{src:"kuma6.gif", value:":kuma6:"},
	{src:"waha.gif", value:":waha:"},
	{src:"hokke.gif", value:":hokke:"},
];

const shift_jis = [
	{display: "ヽ(´ー｀)ノ", value: "[kao]ヽ(´ー｀)ノ[/kao]"},
	{display: "(;´Д`)", value: "[kao](;´Д`)[/kao]"},
	{display: "ヽ(´∇`)ノ", value: "[kao]ヽ(´∇`)ノ[/kao]"},
	{display: "(´人｀)", value: "[kao](´人｀)[/kao]"},
	{display: "(＾Д^)", value: "[kao](＾Д^)[/kao]"},
	{display: "(´ー`)", value: "[kao](´ー`)[/kao]"},
	{display: "（ ´,_ゝ`）", value: "[kao]（ ´,_ゝ`）[/kao]"},
	{display: "(´～`)", value: "[kao](´～`)[/kao]"},
	{display: "(;ﾟДﾟ)", value: "[kao](;ﾟДﾟ)[/kao]"},
	{display: "(;ﾟ∀ﾟ)", value: "[kao](;ﾟ∀ﾟ)[/kao]"},
	{display: "┐(ﾟ～ﾟ)┌", value: "[kao]┐(ﾟ～ﾟ)┌[/kao]"},
	{display: "ヽ(`Д´)ノ", value: "[kao]ヽ(`Д´)ノ[/kao]"},
	{display: "( ´ω`)", value: "[kao]( ´ω`)[/kao]"},
	{display: "(ﾟー｀)", value: "[kao](ﾟー｀)[/kao]"},
	{display: "(・∀・)", value: "[kao](・∀・)[/kao]"},
	{display: "（⌒∇⌒ゞ）", value: "[kao]（⌒∇⌒ゞ）[/kao]"},
	{display: "(ﾟ血ﾟ#)", value: "[kao](ﾟ血ﾟ#)[/kao]"},
	{display: "(ﾟｰﾟ)", value: "[kao](ﾟｰﾟ)[/kao]"},
	{display: "(´￢`)", value: "[kao](´￢`)[/kao]"},
	{display: "(´π｀)", value: "[kao](´π｀)[/kao]"},
	{display: "ヽ(ﾟρﾟ)ノ", value: "[kao]ヽ(ﾟρﾟ)ノ[/kao]"},
	{display: "Σ(;ﾟДﾟ)", value: "[kao]Σ(;ﾟДﾟ)[/kao]"},
	{display: "Σ(ﾟдﾟ|||)", value: "[kao]Σ(ﾟдﾟ|||)[/kao]"},
	{display: "ｷﾀ━━━(・∀・)━━━!!", value: "[kao]ｷﾀ━━━(・∀・)━━━!![/kao]"}
];

const emoji_list = [
	{src:"Grinning-Face-with-Smiling-Eyes.gif", value:"😄", title:"Grinning Face with Smiling Eyes"},
	{src:"Smiling-Face-with-Smiling-Eyes.gif", value:"😊", title:"Smiling Face with Smiling Eyes"},
	{src:"Grinning-Face-with-Big-Eyes.gif", value:"😃", title:"Grinning Face with Big Eyes"},
	{src:"Smiling-Face.gif", value:"☺", title:"Smiling Face"},
	{src:"Winking-Face.gif", value:"😉", title:"Winking Face"},
	{src:"Smiling-Face-with-Heart-Eyes.gif", value:"😍", title:"Smiling Face with Heart Eyes"},
	{src:"Face-Blowing-a-Kiss.gif", value:"😘", title:"Face Blowing a Kiss"},
	{src:"Kissing-Face-with-Closed-Eyes.gif", value:"😚", title:"Kissing Face with Closed Eyes"},
	{src:"Flushed-Face.gif", value:"😳", title:"Flushed Face"},
	{src:"Relieved-Face.gif", value:"😌", title:"Relieved Face"},
	{src:"Beaming-Face-with-Smiling-Eyes.gif", value:"😁", title:"Beaming Face with Smiling Eyes"},
	{src:"Winking-Face-with-Tongue.gif", value:"😜", title:"Winking Face with Tongue"},
	{src:"Squinting-Face-with-Tongue.gif", value:"😝", title:"Squinting Face with Tongue"},
	{src:"Unamused-Face.gif", value:"😒", title:"Unamused Face"},
	{src:"Smirking-Face.gif", value:"😏", title:"Smirking Face"},
	{src:"Sad-but-Relieved-Face.gif", value:"😥", title:"Sad but Relieved Face"},
	{src:"Pensive-Face.gif", value:"😔", title:"Pensive Face"},
	{src:"Disappointed-Face.gif", value:"😞", title:"Disappointed Face"},
	{src:"Confounded-Face.gif", value:"😖", title:"Confounded Face"},
	{src:"Downcast-Face-with-Sweat.gif", value:"😓", title:"Downcast Face with Sweat"},
	{src:"Anxious-Face-with-Sweat.gif", value:"😰", title:"Anxious Face with Sweat"},
	{src:"Fearful-Face.gif", value:"😨", title:"Fearful Face"},
	{src:"Persevering-Face.gif", value:"😣", title:"Persevering Face"},
	{src:"Crying-Face.gif", value:"😢", title:"Crying Face"},
	{src:"Loudly-Crying-Face.gif", value:"😭", title:"Loudly Crying Face"},
	{src:"Face-with-Tears-of-Joy.gif", value:"😂", title:"Face with Tears of Joy"},
	{src:"Astonished-Face.gif", value:"😲", title:"Astonished Face"},
	{src:"Face-Screaming-in-Fear.gif", value:"😱", title:"Face Screaming in Fear"},
	{src:"Angry-Face.gif", value:"😠", title:"Angry Face"},
	{src:"Enraged-Face.gif", value:"😡", title:"Enraged Face"},
	{src:"Face-with-Medical-Mask.gif", value:"😷", title:"Face with Medical Mask"},
	{src:"Sleepy-Face.gif", value:"😪", title:"Sleepy Face"},
	{src:"Red-Heart.gif", value:"❤", title:"Red Heart"},
	{src:"Broken-Heart.gif", value:"💔", title:"Broken Heart"},
	{src:"Beating-Heart.gif", value:"💓", title:"Beating Heart"},
	{src:"Growing-Heart.gif", value:"💗", title:"Growing Heart"},
	{src:"Heart-with-Arrow.gif", value:"💘", title:"Heart with Arrow"},
	{src:"Blue-Heart.gif", value:"💙", title:"Blue Heart"},
	{src:"Green-Heart.gif", value:"💚", title:"Green Heart"},
	{src:"Yellow-Heart.gif", value:"💛", title:"Yellow Heart"},
	{src:"Purple-Heart.gif", value:"💜", title:"Purple Heart"},
	{src:"Red-Exclamation-Mark.gif", value:"❗", title:"Red Exclamation Mark"},
	{src:"White-Exclamation-Mark.gif", value:"❕", title:"White Exclamation Mark"},
	{src:"Red-Question-Mark.gif", value:"❓", title:"Red Question Mark"},
	{src:"White-Question-Mark.gif", value:"❔", title:"White Question Mark"},
	{src:"Musical-Note.gif", value:"🎵", title:"Musical Note"},
	{src:"Musical-Notes.gif", value:"🎶", title:"Musical Notes"},
	{src:"Sparkles.gif", value:"✨", title:"Sparkles"},
	{src:"Star.gif", value:"⭐", title:"Star"},
	{src:"Glowing-Star.gif", value:"🌟", title:"Glowing Star"},
	{src:"Raised-Fist.gif", value:"✊", title:"Raised Fist"},
	{src:"Victory-Hand.gif", value:"✌", title:"Victory Hand"},
	{src:"Raised-Hand.gif", value:"✋", title:"Raised Hand"},
	{src:"Thumbs-Up.gif", value:"👍", title:"Thumbs Up"},
	{src:"Oncoming-Fist.gif", value:"👊", title:"Oncoming Fist"},
	{src:"Index-Pointing-Up.gif", value:"☝", title:"Index Pointing Up"},
	{src:"OK-Hand.gif", value:"👌", title:"OK Hand"},
	{src:"Thumbs-Down.gif", value:"👎", title:"Thumbs Down"},
	{src:"Folded-Hands.gif", value:"🙏", title:"Folded Hands"},
	{src:"Waving-Hand.gif", value:"👋", title:"Waving Hand"},
	{src:"Clapping-Hands.gif", value:"👏", title:"Clapping Hands"},
	{src:"Flexed-Biceps.gif", value:"💪", title:"Flexed Biceps"},
	{src:"Kiss-Mark.gif", value:"💋", title:"Kiss Mark"},
	{src:"Mouth.gif", value:"👄", title:"Mouth"},
	{src:"Eyes.gif", value:"👀", title:"Eyes"},
	{src:"Ear.gif", value:"👂", title:"Ear"},
	{src:"Nose.gif", value:"👃", title:"Nose"},
	{src:"Raising-Hands.gif", value:"🙌", title:"Raising Hands"},
	{src:"Open-Hands.gif", value:"👐", title:"Open Hands"},
	{src:"Person-Gesturing-OK.gif", value:"🙆‍♀️", title:"Person Gesturing OK"},
	{src:"Person-Gesturing-No.gif", value:"🙅‍♀️", title:"Person Gesturing No"},
	{src:"Person-Bowing.gif", value:"🙇‍♂️", title:"Person Bowing"},
	{src:"Footprints.gif", value:"👣", title:"Footprints"},
	{src:"Person-Walking.gif", value:"🚶‍♂️", title:"Person Walking"},
	{src:"Person-Running.gif", value:"🏃‍♂️", title:"Person Running"},
	{src:"Dashing-Away.gif", value:"💨", title:"Dashing Away"},
	{src:"Sweat-Droplets.gif", value:"💦", title:"Sweat Droplets"},
	{src:"Zzz.gif", value:"💤", title:"Zzz"},
	{src:"Anger-Symbol.gif", value:"💢", title:"Anger Symbol"},
	{src:"Crossed-Flags.gif", value:"🎌", title:"Crossed Flags"},
	{src:"Sun.gif", value:"☀", title:"Sun"},
	{src:"Umbrella-with-Rain-Drops.gif", value:"☔", title:"Umbrella with Rain Drops"},
	{src:"Cloud.gif", value:"☁", title:"Cloud"},
	{src:"Snowman-Without-Snow.gif", value:"⛄", title:"Snowman Without Snow"},
	{src:"Crescent-Moon.gif", value:"🌙", title:"Crescent Moon"},
	{src:"High-Voltage.gif", value:"⚡", title:"High Voltage"},
	{src:"Cyclone.gif", value:"🌀", title:"Cyclone"},
	{src:"Water-Wave.gif", value:"🌊", title:"Water Wave"},
	{src:"Rainbow.gif", value:"🌈", title:"Rainbow"},
	{src:"Mount-Fuji.gif", value:"🗻", title:"Mount Fuji"},
	{src:"Cherry-Blossom.gif", value:"🌸", title:"Cherry Blossom"},
	{src:"Tulip.gif", value:"🌷", title:"Tulip"},
	{src:"Maple-Leaf.gif", value:"🍁", title:"Maple Leaf"},
	{src:"Four-Leaf-Clover.gif", value:"🍀", title:"Four Leaf Clover"},
	{src:"Rose.gif", value:"🌹", title:"Rose"},
	{src:"Hibiscus.gif", value:"🌺", title:"Hibiscus"},
	{src:"Sunflower.gif", value:"🌻", title:"Sunflower"},
	{src:"Bouquet.gif", value:"💐", title:"Bouquet"},
	{src:"Palm-Tree.gif", value:"🌴", title:"Palm Tree"},
	{src:"Cactus.gif", value:"🌵", title:"Cactus"},
	{src:"Leaf-Fluttering-in-Wind.gif", value:"🍃", title:"Leaf Fluttering in Wind"},
	{src:"Sheaf-of-Rice.gif", value:"🌾", title:"Sheaf of Rice"},     
	{src:"Fallen-Leaf.gif", value:"🍂", title:"Fallen Leaf"},
	{src:"Cat-Face.gif", value:"🐱", title:"Cat Face"},
	{src:"Dog-Face.gif", value:"🐶", title:"Dog Face"},
	{src:"Pig-Face.gif", value:"🐷", title:"Pig Face"},
	{src:"Mouse-Face.gif", value:"🐭", title:"Mouse Face"},
	{src:"Tiger-Face.gif", value:"🐯", title:"Tiger Face"},
	{src:"Monkey-Face.gif", value:"🐵", title:"Monkey Face"},
	{src:"Bear.gif", value:"🐻", title:"Bear"},
	{src:"Rabbit-Face.gif", value:"🐰", title:"Rabbit Face"},
	{src:"Cow-Face.gif", value:"🐄", title:"Cow Face"},
	{src:"Koala.gif", value:"🐨", title:"Koala"},
	{src:"Hamster.gif", value:"🐹", title:"Hamster"},
	{src:"Frog.gif", value:"🐸", title:"Frog"},
	{src:"Horse.gif", value:"🏇", title:"Horse"},
	{src:"Wolf.gif", value:"🐺", title:"Wolf"},
	{src:"Boar.gif", value:"🐗", title:"Boar"},
	{src:"Two-Hump-Camel.gif", value:"🐫", title:"Two Hump Camel"},
	{src:"Monkey.gif", value:"🐒", title:"Monkey"},
	{src:"Horse-Face.gif", value:"🐎", title:"Horse Face"},
	{src:"Elephant.gif", value:"🐘", title:"Elephant"},
	{src:"Ewe.gif", value:"🐑", title:"Ewe"},
	{src:"Chicken.gif", value:"🐔", title:"Chicken"},
	{src:"Baby-Chick.gif", value:"🐤", title:"Baby Chick"},
	{src:"Bird.gif", value:"🐦", title:"Bird"},
	{src:"Penguin.gif", value:"🐧", title:"Penguin"},
	{src:"Spouting-Whale.gif", value:"🐳", title:"Spouting Whale"},
	{src:"Dolphin.gif", value:"🐬", title:"Dolphin"},
	{src:"Fish.gif", value:"🐟", title:"Fish"},
	{src:"Tropical-Fish.gif", value:"🐠", title:"Tropical Fish"},
	{src:"Octopus.gif", value:"🐙", title:"Octopus"},
	{src:"Snake.gif", value:"🐍", title:"Snake"},
	{src:"Bug.gif", value:"🐛", title:"Bug"},
	{src:"Spiral-Shell.gif", value:"🐚", title:"Spiral Shell"},
	{src:"Wrapped-Gift.gif", value:"🎁", title:"Wrapped Gift"},
	{src:"Birthday-Cake.gif", value:"🎂", title:"Birthday Cake"},
	{src:"Party-Popper.gif", value:"🎉", title:"Party Popper"},
	{src:"Trophy.gif", value:"🏆", title:"Trophy"},
	{src:"Crown.gif", value:"👑", title:"Crown"},
	{src:"Bullseye.gif", value:"🎯", title:"Bullseye"},
	{src:"Bell.gif", value:"🔔", title:"Bell"},
	{src:"Magnifying-Glass-Tilted-Left.gif", value:"🔍", title:"Magnifying Glass Tilted Left"},
	{src:"Light-Bulb.gif", value:"💡", title:"Light Bulb"},
	{src:"Balloon.gif", value:"🎈", title:"Balloon"},
	{src:"Water-Pistol.gif", value:"🔫", title:"Water Pistol"},
	{src:"Hammer.gif", value:"🔨", title:"Hammer"},
	{src:"Fire.gif", value:"🔥", title:"Fire"},
	{src:"Bomb.gif", value:"💣", title:"Bomb"},
	{src:"Loudspeaker.gif", value:"📢", title:"Loudspeaker"},
	{src:"Megaphone.gif", value:"📣", title:"Megaphone"},
	{src:"Soccer-Ball.gif", value:"⚽", title:"Soccer Ball"},
	{src:"Baseball.gif", value:"⚾", title:"Baseball"},
	{src:"Tennis.gif", value:"🎾", title:"Tennis"},
	{src:"Basketball.gif", value:"🏀", title:"Basketball"},
	{src:"Flag-in-Hole.gif", value:"⛳", title:"Flag in Hole"},
	{src:"Skis.gif", value:"🎿", title:"Skis"},
	{src:"American-Football.gif", value:"🏈", title:"American Football"},
	{src:"Pool-8-Ball.gif", value:"🎱", title:"Pool 8 Ball"},
	{src:"Person-Swimming.gif", value:"🏊‍♂️", title:"Person Swimming"},
	{src:"Person-Surfing.gif", value:"🏄‍♂️", title:"Person Surfing"},
	{src:"Speedboat.gif", value:"🚤", title:"Speedboat"},
	{src:"Chequered-Flag.gif", value:"🏁", title:"Chequered Flag"},
	{src:"Mahjong-Red-Dragon.gif", value:"🀄", title:"Mahjong Red Dragon"},
	{src:"Fork-and-Knife.gif", value:"🍴", title:"Fork and Knife"},
	{src:"Beer-Mug.gif", value:"🍺", title:"Beer Mug"},
	{src:"Clinking-Beer-Mugs.gif", value:"🍻", title:"Clinking Beer Mugs"},
	{src:"Cocktail-Glass.gif", value:"🍸", title:"Cocktail Glass"},
	{src:"Sake.gif", value:"🍶", title:"Sake"},
	{src:"Teacup-Without-Handle.gif", value:"🍵", title:"Teacup Without Handle"},
	{src:"Hot-Beverage.gif", value:"☕", title:"Hot Beverage"},
	{src:"Shortcake.gif", value:"🍰", title:"Shortcake"},
	{src:"Soft-Ice-Cream.gif", value:"🍦", title:"Soft Ice Cream"},
	{src:"Dango.gif", value:"🍡", title:"Dango"},
	{src:"Rice-Cracker.gif", value:"🍘", title:"Rice Cracker"},
	{src:"Shaved-Ice.gif", value:"🍧", title:"Shaved Ice"},
	{src:"Rice-Ball.gif", value:"🍙", title:"Rice Ball"},
	{src:"Cooked-Rice.gif", value:"🍚", title:"Cooked Rice"},
	{src:"Bread.gif", value:"🍞", title:"Bread"},
	{src:"Hamburger.gif", value:"🍔", title:"Hamburger"},
	{src:"Curry-Rice.gif", value:"🍛", title:"Curry Rice"},
	{src:"Spaghetti.gif", value:"🍝", title:"Spaghetti"},
	{src:"Steaming-Bowl.gif", value:"🍜", title:"Steaming Bowl"},
	{src:"Sushi.gif", value:"🍣", title:"Sushi"},
	{src:"Bento-Box.gif", value:"🍱", title:"Bento Box"},
	{src:"Pot-of-Food.gif", value:"🍲", title:"Pot of Food"},
	{src:"Oden.gif", value:"🍢", title:"Oden"},
	{src:"French-Fries.gif", value:"🍟", title:"French Fries"},
	{src:"Cooking.gif", value:"🍳", title:"Cooking"},
	{src:"Red-Apple.gif", value:"🍎", title:"Red Apple"},
	{src:"Strawberry.gif", value:"🍓", title:"Strawberry"},
	{src:"Tangerine.gif", value:"🍊", title:"Tangerine"},
	{src:"Watermelon.gif", value:"🍉", title:"Watermelon"},
	{src:"Tomato.gif", value:"🍅", title:"Tomato"},
	{src:"Eggplant.gif", value:"🍆", title:"Eggplant"},
	{src:"Bicycle.gif", value:"🚲", title:"Bicycle"},
	{src:"Automobile.gif", value:"🚗", title:"Automobile"},
	{src:"Sport-Utility-Vehicle.gif", value:"🚙", title:"Sport Utility Vehicle"},
	{src:"Bus.gif", value:"🚌", title:"Bus"},
	{src:"Delivery-Truck.gif", value:"🚚", title:"Delivery Truck"},
	{src:"Police-Car.gif", value:"🚓", title:"Police Car"},
	{src:"Ambulance.gif", value:"🚑", title:"Ambulance"},
	{src:"Fire-Engine.gif", value:"🚒", title:"Fire Engine"},
	{src:"Taxi.gif", value:"🚕", title:"Taxi"},
	{src:"Railway-Car.gif", value:"🚃", title:"Railway Car"},
	{src:"Metro.gif", value:"🚇", title:"Metro"},
	{src:"Station.gif", value:"🚉", title:"Station"},
	{src:"Bullet-Train.gif", value:"🚅", title:"Bullet Train"},
	{src:"High-Speed-Train.gif", value:"🚄", title:"High Speed Train"},
	{src:"Ship.gif", value:"🚢", title:"Ship"},
	{src:"Sailboat.gif", value:"⛵", title:"Sailboat"},
	{src:"Airplane.gif", value:"✈", title:"Airplane"},
	{src:"Rocket.gif", value:"🚀", title:"Rocket"},
	{src:"T-Shirt.gif", value:"👕", title:"T Shirt"},
	{src:"Dress.gif", value:"👗", title:"Dress"},
	{src:"Kimono.gif", value:"👘", title:"Kimono"},
	{src:"Bikini.gif", value:"👙", title:"Bikini"},
	{src:"Necktie.gif", value:"👔", title:"Necktie"},
	{src:"Running-Shoe.gif", value:"👟", title:"Running Shoe"},
	{src:"High-Heeled-Shoe.gif", value:"👠", title:"High Heeled Shoe"},
	{src:"Woman’s-Sandal.gif", value:"👡", title:"Woman’s Sandal"},
	{src:"Woman’s-Boot.gif", value:"👢", title:"Woman’s Boot"},
	{src:"Ribbon.gif", value:"🎀", title:"Ribbon"},
	{src:"Top-Hat.gif", value:"🎩", title:"Top Hat"},
	{src:"Woman’s-Hat.gif", value:"👒", title:"Woman’s Hat"},
	{src:"Handbag.gif", value:"👜", title:"Handbag"},
	{src:"Briefcase.gif", value:"💼", title:"Briefcase"},
	{src:"Closed-Umbrella.gif", value:"🌂", title:"Closed Umbrella"},
	{src:"Ring.gif", value:"💍", title:"Ring"},
	{src:"Gem-Stone.gif", value:"💎", title:"Gem Stone"},
	{src:"Lipstick.gif", value:"💄", title:"Lipstick"},
	{src:"Key.gif", value:"🔑", title:"Key"},
	{src:"Locked.gif", value:"🔒", title:"Locked"},
	{src:"Unlocked.gif", value:"🔓", title:"Unlocked"},
	{src:"Money-Bag.gif", value:"💰", title:"Money Bag"},
	{src:"Open-Book.gif", value:"📖", title:"Open Book"},
	{src:"Memo.gif", value:"📝", title:"Memo"},
	{src:"Scissors.gif", value:"✂", title:"Scissors"},
	{src:"Ten-O’Clock.gif", value:"🕙", title:"Ten O’Clock"},
	{src:"Television.gif", value:"📺", title:"Television"},
	{src:"Laptop.gif", value:"💻", title:"Laptop"},
	{src:"Envelope-with-Arrow.gif", value:"📩", title:"Envelope with Arrow"},
	{src:"Mobile-Phone-with-Arrow.gif", value:"📲", title:"Mobile Phone with Arrow"},
	{src:"Mobile-Phone.gif", value:"📱", title:"Mobile Phone"},
	{src:"Telephone.gif", value:"☎", title:"Telephone"},
	{src:"Fax-Machine.gif", value:"📠", title:"Fax Machine"},
	{src:"Camera.gif", value:"📷", title:"Camera"},
	{src:"Radio.gif", value:"📻", title:"Radio"},
	{src:"Satellite-Antenna.gif", value:"📡", title:"Satellite Antenna"},
	{src:"Speaker-High-Volume.gif", value:"🔊", title:"Speaker High Volume"},
	{src:"Microphone.gif", value:"🎤", title:"Microphone"},
	{src:"Headphone.gif", value:"🎧", title:"Headphone"},
	{src:"Optical-Disk.gif", value:"💿", title:"Optical Disk"},
	{src:"DVD.gif", value:"📀", title:"DVD"},
	{src:"Videocassette.gif", value:"📼", title:"Videocassette"},
	{src:"Computer-Disk.gif", value:"💽", title:"Computer Disk"},
	{src:"Guitar.gif", value:"🎸", title:"Guitar"},
	{src:"Trumpet.gif", value:"🎺", title:"Trumpet"},
	{src:"Saxophone.gif", value:"🎷", title:"Saxophone"},
	{src:"Movie-Camera.gif", value:"🎥", title:"Movie Camera"},
	{src:"Clapper-Board.gif", value:"🎬", title:"Clapper Board"},
	{src:"Ticket.gif", value:"🎫", title:"Ticket"},
	{src:"Artist-Palette.gif", value:"🎨", title:"Artist Palette"},
	{src:"Seat.gif", value:"💺", title:"Seat"},
	{src:"Cigarette.gif", value:"🚬", title:"Cigarette"},
	{src:"No-Smoking.gif", value:"🚭", title:"No Smoking"},
	{src:"Pill.gif", value:"💊", title:"Pill"},
	{src:"Syringe.gif", value:"💉", title:"Syringe"},
	{src:"Toilet.gif", value:"🚽", title:"Toilet"},
	{src:"Barber-Pole.gif", value:"💈", title:"Barber Pole"},
	{src:"Person-Getting-Haircut.gif", value:"💇‍♀️", title:"Person Getting Haircut"},
	{src:"Nail-Polish.gif", value:"💅", title:"Nail Polish"},
	{src:"Person-Getting-Massage.gif", value:"💆‍♀️", title:"Person Getting Massage"},
	{src:"Person-Taking-Bath.gif", value:"🛀", title:"Person Taking Bath"},
	{src:"Woman-Dancing.gif", value:"💃", title:"Woman Dancing"},
	{src:"People-with-Bunny-Ears.gif", value:"👯‍♀️", title:"People with Bunny Ears"},
	{src:"Kiss.gif", value:"💏", title:"Kiss"},
	{src:"Couple-with-Heart.gif", value:"💑", title:"Couple with Heart"},
	{src:"Woman-and-Man-Holding-Hands.gif", value:"👫", title:"Woman and Man Holding Hands"},
	{src:"Boy.gif", value:"👦", title:"Boy"},
	{src:"Girl.gif", value:"👧", title:"Girl"},
	{src:"Man.gif", value:"👨", title:"Man"},
	{src:"Woman.gif", value:"👩", title:"Woman"},
	{src:"Old-Man.gif", value:"👴", title:"Old Man"},
	{src:"Old-Woman.gif", value:"👵", title:"Old Woman"},
	{src:"Baby.gif", value:"👶", title:"Baby"},
	{src:"Person-Tipping-Hand.gif", value:"💁‍♀️", title:"Person Tipping Hand"},
	{src:"Police-Officer.gif", value:"👮", title:"Police Officer"},
	{src:"Construction-Worker.gif", value:"👷‍♂️", title:"Construction Worker"},
	{src:"Personː-Blond-Hair.gif", value:"👱‍♂️", title:"Personː Blond Hair"},
	{src:"Person-with-Skullcap.gif", value:"👲", title:"Person with Skullcap"},
	{src:"Person-Wearing-Turban.gif", value:"👳‍♂️", title:"Person Wearing Turban"},
	{src:"Guard.gif", value:"💂‍♂️", title:"Guard"},
	{src:"Statue-of-Liberty.gif", value:"🗽", title:"Statue of Liberty"},
	{src:"Princess.gif", value:"👸", title:"Princess"},
	{src:"Baby-Angel.gif", value:"👼", title:"Baby Angel"},
	{src:"Angry-Face-with-Horns.gif", value:"👿", title:"Angry Face with Horns"},
	{src:"Ghost.gif", value:"👻", title:"Ghost"},
	{src:"Skull.gif", value:"💀", title:"Skull"},
	{src:"Alien.gif", value:"👽", title:"Alien"},
	{src:"Alien-Monster.gif", value:"👾", title:"Alien Monster"},
	{src:"Pile-of-Poo.gif", value:"💩", title:"Pile of Poo"},
	{src:"Sunrise-Over-Mountains.gif", value:"🌄", title:"Sunrise Over Mountains"},
	{src:"Sunrise.gif", value:"🌅", title:"Sunrise"},
	{src:"Sunset.gif", value:"🌇", title:"Sunset"},
	{src:"Cityscape-at-Dusk.gif", value:"🌆", title:"Cityscape at Dusk"},
	{src:"Night-with-Stars.gif", value:"🌃", title:"Night with Stars"},
	{src:"Pine-Decoration.gif", value:"🎍", title:"Pine Decoration"},
	{src:"Heart-with-Ribbon.gif", value:"💝", title:"Heart with Ribbon"},
	{src:"Japanese-Dolls.gif", value:"🎎", title:"Japanese Dolls"},
	{src:"Graduation-Cap.gif", value:"🎓", title:"Graduation Cap"},
	{src:"Backpack.gif", value:"🎒", title:"Backpack"},
	{src:"Carp-Streamer.gif", value:"🎏", title:"Carp Streamer"},
	{src:"Fireworks.gif", value:"🎆", title:"Fireworks"},
	{src:"Sparkler.gif", value:"🎇", title:"Sparkler"},
	{src:"Wind-Chime.gif", value:"🎐", title:"Wind Chime"},
	{src:"Moon-Viewing-Ceremony.gif", value:"🎑", title:"Moon Viewing Ceremony"},
	{src:"Jack-O-Lantern.gif", value:"🎃", title:"Jack O Lantern"},
	{src:"Santa-Claus.gif", value:"🎅", title:"Santa Claus"},
	{src:"Christmas-Tree.gif", value:"🎄", title:"Christmas Tree"},
	{src:"House.gif", value:"🏠", title:"House"},
	{src:"Office-Building.gif", value:"🏢", title:"Office Building"},
	{src:"Closed-Mailbox-with-Raised-Flag.gif", value:"📫", title:"Closed Mailbox with Raised Flag"},
	{src:"Postbox.gif", value:"📮", title:"Postbox"},
	{src:"Japanese-Post-Office.gif", value:"🏣", title:"Japanese Post Office"},
	{src:"Bank.gif", value:"🏦", title:"Bank"},
	{src:"ATM-Sign.gif", value:"🏧", title:"ATM Sign"},
	{src:"Hospital.gif", value:"🏥", title:"Hospital"},
	{src:"Convenience-Store.gif", value:"🏪", title:"Convenience Store"},
	{src:"School.gif", value:"🏫", title:"School"},
	{src:"Hotel.gif", value:"🏨", title:"Hotel"},
	{src:"Love-Hotel.gif", value:"🏩", title:"Love Hotel"},
	{src:"Department-Store.gif", value:"🏬", title:"Department Store"},
	{src:"Wedding.gif", value:"💒", title:"Wedding"},
	{src:"Church.gif", value:"⛪", title:"Church"},
	{src:"Japanese-Castle.gif", value:"🏯", title:"Japanese Castle"},
	{src:"Castle.gif", value:"🏰", title:"Castle"},
	{src:"Tokyo-Tower.gif", value:"🗼", title:"Tokyo Tower"},
	{src:"Shibuya.gif", value:"🛍", title:"Shibuya"},
	{src:"Factory.gif", value:"🏭", title:"Factory"},
	{src:"Ferris-Wheel.gif", value:"🎡", title:"Ferris Wheel"},
	{src:"Roller-Coaster.gif", value:"🎢", title:"Roller Coaster"},
	{src:"Fountain.gif", value:"⛲", title:"Fountain"},
	{src:"Tent.gif", value:"⛺", title:"Tent"},
	{src:"Hot-Springs.gif", value:"♨", title:"Hot Springs"},
	{src:"Fuel-Pump.gif", value:"⛽", title:"Fuel Pump"},
	{src:"Bus-Stop.gif", value:"🚏", title:"Bus Stop"},
	{src:"Horizontal-Traffic-Light.gif", value:"🚥", title:"Horizontal Traffic Light"},
	{src:"Warning.gif", value:"⚠", title:"Warning"},
	{src:"Construction.gif", value:"🚧", title:"Construction"},
	{src:"Japanese-Symbol-for-Beginner.gif", value:"🔰", title:"Japanese Symbol for Beginner"},
	{src:"P-Button.gif", value:"🅿", title:"P Button"},
	{src:"Restroom.gif", value:"🚻", title:"Restroom"},
	{src:"Water-Closet.gif", value:"🚾", title:"Water Closet"},
	{src:"Wheelchair-Symbol.gif", value:"♿", title:"Wheelchair Symbol"},
	{src:"Cinema.gif", value:"🎦", title:"Cinema"},
	{src:"Men’s-Room.gif", value:"🚹", title:"Men’s Room"},
	{src:"Women’s-Room.gif", value:"🚺", title:"Women’s Room"},
	{src:"Baby-Symbol.gif", value:"🚼", title:"Baby Symbol"},
	{src:"Twelve-O’Clock.gif", value:"🕛", title:"Twelve O’Clock"},
	{src:"One-O’Clock.gif", value:"🕐", title:"One O’Clock"},
	{src:"Two-O’Clock.gif", value:"🕑", title:"Two O’Clock"},
	{src:"Three-O’Clock.gif", value:"🕒", title:"Three O’Clock"},
	{src:"Four-O’Clock.gif", value:"🕓", title:"Four O’Clock"},
	{src:"Five-O’Clock.gif", value:"🕔", title:"Five O’Clock"},
	{src:"Six-O’Clock.gif", value:"🕕", title:"Six O’Clock"},
	{src:"Seven-O’Clock.gif", value:"🕖", title:"Seven O’Clock"},
	{src:"Eight-O’Clock.gif", value:"🕗", title:"Eight O’Clock"},
	{src:"Nine-O’Clock.gif", value:"🕘", title:"Nine O’Clock"},
	{src:"Eleven-O’Clock.gif", value:"🕚", title:"Eleven O’Clock"},
	{src:"Hollow-Red-Circle.gif", value:"⭕", title:"Hollow Red Circle"},
	{src:"Cross-Mark.gif", value:"❌", title:"Cross Mark"},
	{src:"Heart-Suit.gif", value:"♥", title:"Heart Suit"},
	{src:"Diamond-Suit.gif", value:"♦", title:"Diamond Suit"},
	{src:"Spade-Suit.gif", value:"♠", title:"Spade Suit"},
	{src:"Club-Suit.gif", value:"♣", title:"Club Suit"},
	{src:"Up-Right-Arrow.gif", value:"↗", title:"Up Right Arrow"},
	{src:"Up-Left-Arrow.gif", value:"↖", title:"Up Left Arrow"},
	{src:"Down-Right-Arrow.gif", value:"↘", title:"Down Right Arrow"},
	{src:"Down-Left-Arrow.gif", value:"↙", title:"Down Left Arrow"},
	{src:"Up-Arrow.gif", value:"⬆", title:"Up Arrow"},
	{src:"Down-Arrow.gif", value:"⬇", title:"Down Arrow"},
	{src:"Right-Arrow.gif", value:"➡", title:"Right Arrow"},
	{src:"Left-Arrow.gif", value:"⬅", title:"Left Arrow"},
	{src:"Play-Button.gif", value:"▶", title:"Play Button"},
	{src:"Reverse-Button.gif", value:"◀", title:"Reverse Button"},
	{src:"Fast-Forward-Button.gif", value:"⏩", title:"Fast Forward Button"},
	{src:"Fast-Reverse-Button.gif", value:"⏪", title:"Fast Reverse Button"},
	{src:"Backhand-Index-Pointing-Up.gif", value:"👆", title:"Backhand Index Pointing Up"},
	{src:"Backhand-Index-Pointing-Down.gif", value:"👇", title:"Backhand Index Pointing Down"},
	{src:"Backhand-Index-Pointing-Left.gif", value:"👈", title:"Backhand Index Pointing Left"},
	{src:"Backhand-Index-Pointing-Right.gif", value:"👉", title:"Backhand Index Pointing Right"},
	{src:"Keycap-Digit-One.gif", value:"1️⃣", title:"Keycap Digit One"},
	{src:"Keycap-Digit-Two.gif", value:"2️⃣", title:"Keycap Digit Two"},
	{src:"Keycap-Digit-Three.gif", value:"3️⃣", title:"Keycap Digit Three"},
	{src:"Keycap-Digit-Four.gif", value:"4️⃣", title:"Keycap Digit Four"},
	{src:"Keycap-Digit-Five.gif", value:"5️⃣", title:"Keycap Digit Five"},
	{src:"Keycap-Digit-Six.gif", value:"6️⃣", title:"Keycap Digit Six"},
	{src:"Keycap-Digit-Seven.gif", value:"7️⃣", title:"Keycap Digit Seven"},
	{src:"Keycap-Digit-Eight.gif", value:"8️⃣", title:"Keycap Digit Eight"},
	{src:"Keycap-Digit-Nine.gif", value:"9️⃣", title:"Keycap Digit Nine"},
	{src:"Keycap-Digit-Zero.gif", value:"0️⃣", title:"Keycap Digit Zero"},
	{src:"Keycap-Number-Sign.gif", value:"#️⃣", title:"Keycap Number Sign"},
	{src:"Dotted-Six-Pointed-Star.gif", value:"🔯", title:"Dotted Six Pointed Star"},
	{src:"Aries.gif", value:"♈", title:"Aries"},
	{src:"Taurus.gif", value:"♉", title:"Taurus"},
	{src:"Gemini.gif", value:"♊", title:"Gemini"},
	{src:"Cancer.gif", value:"♋", title:"Cancer"},
	{src:"Leo.gif", value:"♌", title:"Leo"},
	{src:"Virgo.gif", value:"♍", title:"Virgo"},
	{src:"Libra.gif", value:"♎", title:"Libra"},
	{src:"Scorpio.gif", value:"♏", title:"Scorpio"},
	{src:"Sagittarius.gif", value:"♐", title:"Sagittarius"},
	{src:"Capricorn.gif", value:"♑", title:"Capricorn"},
	{src:"Aquarius.gif", value:"♒", title:"Aquarius"},
	{src:"Pisces.gif", value:"♓", title:"Pisces"},
	{src:"Ophiuchus.gif", value:"⛎", title:"Ophiuchus"},
	{src:"A-Button-(Blood-Type).gif", value:"🅰", title:"A Button (Blood Type)"},
	{src:"B-Button-(Blood-Type).gif", value:"🅱", title:"B Button (Blood Type)"},
	{src:"AB-Button-(Blood-Type).gif", value:"🆎", title:"AB Button (Blood Type)"},
	{src:"O-Button-(Blood-Type).gif", value:"🅾", title:"O Button (Blood Type)"},
	{src:"Flagː-Japan.gif", value:"🇯🇵", title:"Flagː Japan"},
	{src:"Flagː-United-States.gif", value:"🇺🇸", title:"Flagː United States"},
	{src:"Flagː-France.gif", value:"🇫🇷", title:"Flagː France"},
	{src:"Flagː-Germany.gif", value:"🇩🇪", title:"Flagː Germany"},
	{src:"Flagː-Italy.gif", value:"🇮🇹", title:"Flagː Italy"},
	{src:"Flagː-United-Kingdom.gif", value:"🇬🇧", title:"Flagː United Kingdom"},
	{src:"Flagː-Spain.gif", value:"🇪🇸", title:"Flagː Spain"},
	{src:"Flagː-Russia.gif", value:"🇷🇺", title:"Flagː Russia"},
	{src:"Flagː-China.gif", value:"🇨🇳", title:"Flagː China"},
	{src:"Flagː-South-Korea.gif", value:"🇰🇷", title:"Flagː South Korea"},
	{src:"Chart-Increasing-with-Yen.gif", value:"💹", title:"Chart Increasing with Yen"},
	{src:"Currency-Exchange.gif", value:"💱", title:"Currency Exchange"},
	{src:"Slot-Machine.gif", value:"🎰", title:"Slot Machine"},
	{src:"OK-Button.gif", value:"🆗", title:"OK Button"},
	{src:"Top-Arrow.gif", value:"🔝", title:"Top Arrow"},
	{src:"New-Button.gif", value:"🆕", title:"New Button"},
	{src:"Up!-Button.gif", value:"🆙", title:"Up! Button"},
	{src:"Cool-Button.gif", value:"🆒", title:"Cool Button"},
	{src:"Japanese-“Here”-Button.gif", value:"🈁", title:"Japanese “Here” Button"},
	{src:"Vs-Button.gif", value:"🆚", title:"Vs Button"},
	{src:"Japanese-“No-Vacancy”-Button.gif", value:"🈵", title:"Japanese “No Vacancy” Button"},
	{src:"Japanese-“Vacancy”-Button.gif", value:"🈳", title:"Japanese “Vacancy” Button"},
	{src:"Japanese-“Bargain”-Button.gif", value:"🉐", title:"Japanese “Bargain” Button"},
	{src:"Japanese-“Discount”-Button.gif", value:"🈹", title:"Japanese “Discount” Button"},
	{src:"Japanese-“Service-Charge”-Button.gif", value:"🈂", title:"Japanese “Service Charge” Button"},
	{src:"Japanese-“Reserved”-Button.gif", value:"🈯", title:"Japanese “Reserved” Button"},
	{src:"Japanese-“Open-for-Business”-Button.gif", value:"🈺", title:"Japanese “Open for Business” Button"},
	{src:"ID-Button.gif", value:"🆔", title:"ID Button"},
	{src:"Japanese-“Not-Free-of-Charge”-Button.gif", value:"🈶", title:"Japanese “Not Free of Charge” Button"},
	{src:"Japanese-“Free-of-Charge”-Button.gif", value:"🈚", title:"Japanese “Free of Charge” Button"},
	{src:"Japanese-“Monthly-Amount”-Button.gif", value:"🈷", title:"Japanese “Monthly Amount” Button"},
	{src:"Japanese-“Application”-Button.gif", value:"🈸", title:"Japanese “Application” Button"},
	{src:"Japanese-“Congratulations”-Button.gif", value:"㊗", title:"Japanese “Congratulations” Button"},
	{src:"Japanese-“Secret”-Button.gif", value:"㊙", title:"Japanese “Secret” Button"},
	{src:"No-One-Under-Eighteen.gif", value:"🔞", title:"No One Under Eighteen"},
	{src:"Trident-Emblem.gif", value:"🔱", title:"Trident Emblem"},
	{src:"Part-Alternation-Mark.gif", value:"〽", title:"Part Alternation Mark"},
	{src:"Red-Circle.gif", value:"🔴", title:"Red Circle"},
	{src:"Heart-Decoration.gif", value:"💟", title:"Heart Decoration"},
	{src:"Eight-Pointed-Star.gif", value:"✴", title:"Eight Pointed Star"},
	{src:"Eight-Spoked-Asterisk.gif", value:"✳", title:"Eight Spoked Asterisk"},
	{src:"Black-Square-Button.gif", value:"🔲", title:"Black Square Button"},
	{src:"White-Square-Button.gif", value:"🔳", title:"White Square Button"},
	{src:"Antenna-Bars.gif", value:"📶", title:"Antenna Bars"},
	{src:"Vibration-Mode.gif", value:"📳", title:"Vibration Mode"},
	{src:"Mobile-Phone-Off.gif", value:"📴", title:"Mobile Phone Off"},
	{src:"Double-Curly-Loop.gif", value:"➿", title:"Double Curly Loop"},
	{src:"Copyright.gif", value:"©", title:"Copyright"},
	{src:"Registered.gif", value:"®", title:"Registered"},
	{src:"Trade-Mark.gif", value:"™", title:"Trade Mark"},
]

const bbcode = [
	{meaning: "<b>B</b>", title: "Bold", code:"b"},
	{meaning: "<i>I</i>", title: "Italics", code:"i"},
	{meaning: "<u>U</u>", title: "Underline", code:"u"},
	{meaning: "<s>S</s>", title: "Strikethrough", code:"s"},
	{meaning: "<span style='background-color:black;color:white'>Spoiler</span>", title: "Spoiler", code:"spoiler"},
	{meaning: "<q>Quote</q>", title: "Blockquote", code:"quote"},
	{meaning: "Scroll", title: "Scrollbar for long passages of text", code:"scroll"},
];

const selector_bbcode = [ // BBCodes with selectors
	{meaning: "<code class='code'>Code</code>", title: "Code", code: "code", selector: ""},
	{meaning:"<span style='font-weight:bold'><span class='bokuRed'>C</span><span class='bokuGreen'>o</span><span class='bokuRed'>l</span><span class='bokuGreen'>o</span><span class='bokuRed'>r</span>", title:"Font color", code:"color", selector:"#800043"},
	{meaning:"Size", title: "Font size", code:"s", selector:"3"},
	{meaning: "<pre style='display:inline'>ASCII</pre>", title: "ASCII art", selector:"ASCII (monospace)"},
];

let savedSelection = { start: 0, end: 0 };

/*
add text to where the cursor is located in a textarea (and focus on it)
this also replaces any selected text!
this function taken from https://stackoverflow.com/a/11077016
with minor changes
myField: which textarea to change (in this file, it's gonna be COMMENT)
myValue: what text to add
*/
function insertAtCursor(myField, myValue) {
	//IE support
	if (document.selection) {
		myField.focus();
		sel = document.selection.createRange();
		sel.text = myValue;
	}
	//MOZILLA and others
	else if (myField.selectionStart || myField.selectionStart === 0) {
		var startPos = myField.selectionStart;
		var endPos = myField.selectionEnd;
		myField.value = myField.value.substring(0, startPos)
			+ myValue
			+ myField.value.substring(endPos, myField.value.length);
	myField.selectionStart = startPos + myValue.length;
	myField.selectionEnd = startPos + myValue.length;
	myField.focus();
	} else {
		myField.value += myValue;
	}
}

function wrapSelectionWithTags(textarea, tag, value = null) {
	let start = textarea.selectionStart;
	let end = textarea.selectionEnd;
	let selectedText = textarea.value.substring(start, end);

	let openingTag = value ? `[${tag}=${value}]` : `[${tag}]`;
	let closingTag = `[/${tag}]`;

	// Special handling for [s7]...[/s7] format
	if (tag === "s" && value !== null) {
		openingTag = `[s${value}]`;
		closingTag = `[/s${value}]`;
	} else if (value !== null) {
		openingTag = `[${tag}=${value}]`;
		closingTag = `[/${tag}]`;
	} else {
		openingTag = `[${tag}]`;
		closingTag = `[/${tag}]`;
	}

	let newText = textarea.value.substring(0, start) +
		openingTag + selectedText + closingTag +
		textarea.value.substring(end);

	textarea.value = newText;

	// Adjust selection to remain around the newly wrapped text
	textarea.focus();
	if (selectedText.length > 0) {
		textarea.selectionStart = start;
		textarea.selectionEnd = end + openingTag.length + closingTag.length;
	} else {
		// If no text was selected, place cursor between the new tags
		let cursor = start + openingTag.length;
		textarea.selectionStart = cursor;
		textarea.selectionEnd = cursor;
	}
}

/* CONSTANTS */
const NUM_EMOTES = emotes_list.length;
const NUM_EMOJI = emoji_list.length;
const COMMENT = document.getElementById("com");

/* FUNCTIONS */
const onClickHandler = (e) => insertAtCursor(COMMENT, e.target.title); //COMMENT.value += e.target.title;
const onClickHandler2 = (e) => insertAtCursor(COMMENT, e.currentTarget.value); //COMMENT.value += e.currentTarget.value;

const SUMMARY_ELEMENT = (summary_title) => {
	return "<summary style='width:fit-content;height:fit-content'"+
				 "onMouseOver='this.style.fontWeight=`bold`;this.style.cursor=`pointer`'"+
				 "onMouseOut='this.style.fontWeight=`normal`'>"+summary_title+"</summary>";
};
const insertBBCode = () => {
	if (text_input.value === "") {
		return;
	} else {
		let formatted_str = "";

		// get all checkboxes
		let checkboxes = document.querySelectorAll("#bbcodeContainer > label > input[type=checkbox]");

		// run through all checkboxes
		checkboxes.forEach((checkbox,index) => {
			if (checkbox.checked) {
				switch(index) {
					case 8: // COLOR
						formatted_str += "["+selector_bbcode[0].code+"="+selector_bbcode[0].selector+"]";
						break;
					case 9: // SIZE
						formatted_str += "["+selector_bbcode[1].code+selector_bbcode[1].selector+"]";
						break;
					default:
						//console.log(checkbox,"checkbox");
						formatted_str += "["+bbcode[index].code+"]";
						break;
				}
			}
		})

		// add string in text input after running through checkboxes
		formatted_str += text_input.value;

		// check in reverse and add closing tags
		for (let index=checkboxes.length; index>=0;index--) {
			if (checkboxes[index]?.checked) { // '?.'-https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Optional_chaining
				switch(index) {
					case 8: // COLOR
						formatted_str += "[/"+selector_bbcode[0].code+"]";
						break;
					case 9: // SIZE
						formatted_str += "[/"+selector_bbcode[1].code+selector_bbcode[1].selector+"]";
						break;
					default:
						//console.log(checkbox,"checkbox");
						formatted_str += "[/"+bbcode[index].code+"]";
						break;
				}
			}
		}

		// add the BBCode formatted string to the comment field
		insertAtCursor(COMMENT, formatted_str); //COMMENT.value += formatted_str;
	}
};

/* EMOTES */
let emotes_container = document.createElement("details");
emotes_container.innerHTML += SUMMARY_ELEMENT("Emotes");
emotes_container.id = "emotesContainer";
emotes_container.classList.add("formattingDetails");

emotes_list.forEach((emote,index) => {
	let button = document.createElement('button');
	button.type = "button";
	button.classList.add("buttonEmote");
	button.title = emote.value;
	button.classList.add("emoteButton");
	button.innerHTML += '<img class="emoteImage" src="'+STATIC_URL+'image/emote/'+emote.src+'" loading="lazy" title="'+emote.value+'" alt="'+emote.value+'">';
	button.addEventListener("click", onClickHandler);
	emotes_container.appendChild(button);
});

// A.after(B), B.after(C), etc... = A --> B --> C --> ...
COMMENT?.after(emotes_container); // insert emotes after comment box

/* EMOJI */
let emoji_container = document.createElement("details");
emoji_container.innerHTML += SUMMARY_ELEMENT("Emoji");
emoji_container.id = "emojiContainer";
emoji_container.classList.add("formattingDetails");
emoji_list.forEach((emoji, index) => {
    let button = document.createElement('button');
    button.type = "button";
    button.classList.add("buttonEmoji");
    button.title = emoji.title;
    button.value = emoji.value;
    button.classList.add("emojiButton");
    button.innerHTML += '<img class="emojiImage" src="'+STATIC_URL+'image/emoji/'+emoji.src+'" loading="lazy" title="'+emoji.title+'" alt="'+emoji.title+'" height="24px">';
    button.addEventListener("click", onClickHandler2);
    emoji_container.appendChild(button);
    // Add margin at the end of every 7th row (after every 70 emoji)
    if ((index + 1) % 70 === 0) {
        button.classList.add('row-end'); // Add the 'row-end' class to create a gap
    }
});
emotes_container.after(emoji_container);

/* SHIFT_JIS */
let sjis_container = document.createElement("details");
sjis_container.innerHTML += SUMMARY_ELEMENT("Kaomoji");
sjis_container.id = "kaomojiContainer";
sjis_container.classList.add("formattingDetails");

shift_jis.forEach((sjis, index) => {
	let button = document.createElement('button');
	button.type = "button";
	button.classList.add("buttonSJIS");
	button.title = sjis.value;
	button.dataset.value = sjis.value;
	button.classList.add("kaomojiButton");
	button.innerHTML += '<div class="ascii" title="' + sjis.value + '">' + sjis.display + '</div>';
	button.addEventListener("click", onClickHandler);
	sjis_container.appendChild(button);
});
emoji_container.after(sjis_container); // insert sjis after emoji

/* BBCODE */
let bbcode_container = document.createElement("details");
bbcode_container.id = "bbcodeContainer";
bbcode_container.classList.add("formattingDetails");
bbcode_container.innerHTML += SUMMARY_ELEMENT("BBCode");

let bbcode_button_container = document.createElement("div");
bbcode_button_container.id = "bbcodeButtonContainer";

bbcode.forEach((code) => {
	let button = document.createElement("button");
	button.classList.add("bbcodeButton");
	button.type = "button";
	button.innerHTML = code.meaning;
	button.title = code.title;
	button.addEventListener("click", () => {
		wrapSelectionWithTags(COMMENT, code.code);
	});
	bbcode_button_container.appendChild(button);
});

bbcode_container.appendChild(bbcode_button_container);

const [codeBtn, codeInput] = createSelectorButton(selector_bbcode[0], "code");
bbcode_button_container.appendChild(codeBtn);
bbcode_button_container.appendChild(codeInput);

const [colorBtn, colorInput] = createSelectorButton(selector_bbcode[1], "color");
bbcode_button_container.appendChild(colorBtn);
bbcode_button_container.appendChild(colorInput);

const [sizeBtn, sizeInput] = createSelectorButton(selector_bbcode[2], "size");
bbcode_button_container.appendChild(sizeBtn);
bbcode_button_container.appendChild(sizeInput);

const [preBtn, preInput] = createSelectorButton(selector_bbcode[3], "pre");
bbcode_button_container.appendChild(preBtn);
bbcode_button_container.appendChild(preInput);

function positionElementNear(trigger, targetElement) {
	document.body.appendChild(targetElement); // ensure it's in the DOM
	targetElement.style.display = "block";

	let rect = trigger.getBoundingClientRect();
	let inputWidth = targetElement.offsetWidth;
	let left = rect.left + window.scrollX;
	let top = rect.bottom + window.scrollY;
	let maxLeft = window.innerWidth + window.scrollX - inputWidth;

	if (left > maxLeft) left = maxLeft;
	if (left < 0) left = 0;

	targetElement.style.left = `${left}px`;
	targetElement.style.top = `${top}px`;
	targetElement.focus();
}

function createSelectorButton(selectorConfig, type) {
	let button = document.createElement("button");
	button.classList.add("bbcodeButton");
	button.type = "button";
	button.innerHTML = selectorConfig.meaning;
	button.title = selectorConfig.title;

	let input;
	let saved = { start: 0, end: 0 };

	if (type === "color") {
		input = document.createElement("input");
		input.type = "color";
		input.value = selectorConfig.selector;
	} else if (type === "size") {
		input = document.createElement("select");

		let placeholder = document.createElement("option");
		placeholder.disabled = true;
		placeholder.selected = true;
		placeholder.hidden = true;
		placeholder.textContent = "Select a size";
		input.appendChild(placeholder);

		const sizeLabels = {
			1: "Tiny", 2: "Small", 3: "Normal",
			4: "Large", 5: "Larger", 6: "Huge", 7: "Massive"
		};

		for (let i = 1; i <= 7; i++) {
			let opt = document.createElement("option");
			opt.value = i;
			opt.textContent = `${sizeLabels[i]} (${i})`;
			opt.className = `fontSize${i}`;
			input.appendChild(opt);
		}
	} else if (type === "pre") {
		input = document.createElement("select");

		let placeholder = document.createElement("option");
		placeholder.disabled = true;
		placeholder.selected = true;
		placeholder.hidden = true;
		placeholder.textContent = "Select format";
		input.appendChild(placeholder);

		const preOptions = [
			{ label: "ASCII (monospace)", tag: "pre" },
			{ label: "Shift-JIS (2ch)", tag: "aa" },
			{ label: "Shift-JIS (Ayashii)", tag: "sw" }
		];

		preOptions.forEach((option) => {
			let opt = document.createElement("option");
			opt.value = option.tag;
			opt.textContent = option.label;
			input.appendChild(opt);
		});
	} else if (type === "code") {
		input = document.createElement("select");

		let placeholder = document.createElement("option");
		placeholder.disabled = true;
		placeholder.selected = true;
		placeholder.hidden = true;
		placeholder.textContent = "Select a language";
		input.appendChild(placeholder);

		const langs = [
			{label: "C", value: "c"},
			{label: "C++", value: "cpp"},
			{label: "PHP", value: "php"},
			{label: "JavaScript", value: "js"},
			{label: "Python", value: "py"},
			{label: "Perl", value: "pl"},
			{label: "Fortran", value: "f"},
			{label: "HTML", value: "html"},
			{label: "CSS", value: "css"},
			{label: "Other", value: "other"}
		];

		langs.forEach(lang => {
			let opt = document.createElement("option");
			opt.value = lang.value;
			opt.textContent = lang.label;
			input.appendChild(opt);
		});
	}

	input.style.display = "none";
	input.style.position = "absolute";
	input.style.zIndex = "1000";

	button.addEventListener("click", (e) => {
		saved.start = COMMENT.selectionStart;
		saved.end = COMMENT.selectionEnd;
		positionElementNear(e.target, input);
	});

	input.addEventListener("change", (e) => {
		COMMENT.focus();
		COMMENT.setSelectionRange(saved.start, saved.end);

		let val = e.target.value;

		if (type === "code") {
			if (val === "other") {
				wrapSelectionWithTags(COMMENT, "code");
			} else {
				wrapSelectionWithTags(COMMENT, "code", val);
			}
		} else if (type !== "pre") {
			selectorConfig.selector = val;
			wrapSelectionWithTags(COMMENT, selectorConfig.code, val);
		} else {
			wrapSelectionWithTags(COMMENT, val);
		}

		input.style.display = "none";
	});

	input.addEventListener("blur", () => {
		input.style.display = "none";
	});

	return [button, input];
}

sjis_container.after(bbcode_container);
