<?php if( !defined('WPINC') ) die; ?>
<div class="engb-scale-block">
    <div class="engb-scale-circle p<?php echo $scale['percentage'];?>">
        <span><?php echo $scale['percentage'];?>%</span>
        <div class="left-half-clipper">
            <div class="first50-bar"></div><div class="value-bar"></div>
        </div>
    </div>
    <?php if($scale['delta'] > 0 ) { ?>
        <div class="engb-scale-label">
            <?php printf(__('Out of %s %s', 'leyka_engb'), "<b>".$scale['target']."</b>", $scale['currency']); ?>
        </div>
    <?php } ?>
</div>