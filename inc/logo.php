<?php
// Logo: panelden görsel yüklenmişse o, yoksa Montserrat ile aralıklı yazı.
$__b = content()['brand'] ?? [];
if (!empty($__b['logo'])): ?><img class="logo logo--img" src="<?= e($__b['logo']) ?>" alt="<?= e($__b['name'] ?? 'İSE ATÖLYE') ?>"><?php else: ?><span class="logo"><?= e($__b['logo_text'] ?? 'İSE ATÖLYE') ?></span><?php endif;
