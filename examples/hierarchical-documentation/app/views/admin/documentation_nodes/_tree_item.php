<div><?php
	echo $object->title.' (';
	echo $this->html->documentation_node_link($object, array('text' => 'View'));
	echo '/';
	echo $this->html->admin_object_link($object, array('controller' => 'documentation_nodes', 'action' => 'edit', 'text' => 'Edit'));
	echo ')'; ?>
</div>