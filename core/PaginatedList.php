<?php
/**
 * A decorator that wraps around a data list in order to provide pagination.
 *
 * @package framework
 * @subpackage view
 */
class PaginatedList extends SS_ListDecorator {

	protected $request;
	protected $getVar = 'start';

	protected $pageLength = 10;
	protected $pageStart;
	protected $totalItems;

	/**
	 * Constructs a new paginated list instance around a list.
	 *
	 * @param SS_List $list The list to paginate. The getRange method will
	 *        be used to get the subset of objects to show.
	 * @param array|ArrayAccess Either a map of request parameters or
	 *        request object that the pagination offset is read from.
	 */
	public function __construct(SS_List $list, $request = array()) {
		if (!is_array($request) && !$request instanceof ArrayAccess) {
			throw new Exception('The request must be readable as an array.');
		}

		$this->request = $request;
		parent::__construct($list);
	}

	/**
	 * Returns the GET var that is used to set the page start. This defaults
	 * to "start".
	 *
	 * If there is more than one paginated list on a page, it is neccesary to
	 * set a different get var for each using {@link setPaginationGetVar()}.
	 *
	 * @return string
	 */
	public function getPaginationGetVar() {
		return $this->getVar;
	}

	/**
	 * Sets the GET var used to set the page start.
	 *
	 * @param string $var
	 */
	public function setPaginationGetVar($var) {
		$this->getVar = $var;
	}

	/**
	 * Returns the number of items displayed per page. This defaults to 10.
	 *
	 * @return int.
	 */
	public function getPageLength() {
		return $this->pageLength;
	}

	/**
	 * Set the number of items displayed per page.
	 *
	 * @param int $length
	 */
	public function setPageLength($length) {
		$this->pageLength = $length;
	}

	/**
	 * Sets the current page.
	 *
	 * @param int $page
	 */
	public function setCurrentPage($page) {
		$this->pageStart = ($page - 1) * $this->pageLength;
	}

	/**
	 * Returns the offset of the item the current page starts at.
	 *
	 * @return int
	 */
	public function getPageStart() {
		if ($this->pageStart === null) {
			if ($this->request && isset($this->request[$this->getVar])) {
				$this->pageStart = (int) $this->request[$this->getVar];
			} else {
				$this->pageStart = 0;
			}
		}

		return $this->pageStart;
	}

	/**
	 * Sets the offset of the item that current page starts at. This should be
	 * a multiple of the page length.
	 *
	 * @param int $start
	 */
	public function setPageStart($start) {
		$this->pageStart = $start;
	}

	/**
	 * Returns the total number of items in the unpaginated list.
	 *
	 * @return int
	 */
	public function getTotalItems() {
		if ($this->totalItems === null) {
			$this->totalItems = count($this->list);
		}

		return $this->totalItems;
	}

	/**
	 * Sets the total number of items in the list. This is useful when doing
	 * custom pagination.
	 *
	 * @param int $items
	 */
	public function setTotalItems($items) {
		$this->totalItems = $items;
	}

	/**
	 * Sets the page length, page start and total items from a query object's
	 * limit, offset and unlimited count. The query MUST have a limit clause.
	 *
	 * @param SQLQuery $query
	 */
	public function setPaginationFromQuery(SQLQuery $query) {
		if ($limit = $query->getLimit()) {
			$this->setPageLength($limit['limit']);
			$this->setPageStart($limit['start']);
			$this->setTotalItems($query->unlimitedRowCount());
		}
	}

	/**
	 * @return IteratorIterator
	 */
	public function getIterator() {
		return new IteratorIterator(
			$this->list->limit($this->pageLength, $this->getPageStart())
		);
	}

	/**
	 * Returns a set of links to all the pages in the list. This is useful for
	 * basic pagination.
	 *
	 * By default it returns links to every page, but if you pass the $max
	 * parameter the number of pages will be limited to that number, centered
	 * around the current page.
	 *
	 * @param  int $max
	 * @return SS_List
	 */
	public function Pages($max = null) {
		$result = new ArrayList();

		if ($max) {
			$start = ($this->CurrentPage() - floor($max / 2)) - 1;
			$end   = $this->CurrentPage() + floor($max / 2);

			if ($start < 0) {
				$start = 0;
				$end   = $max;
			}

			if ($end > $this->TotalPages()) {
				$end   = $this->TotalPages();
				$start = max(0, $end - $max);
			}
		} else {
			$start = 0;
			$end   = $this->TotalPages();
		}

		for ($i = $start; $i < $end; $i++) {
			$result->push(new ArrayData(array(
				'PageNum'     => $i + 1,
				'Link'        => HTTP::setGetVar($this->getVar, $i * $this->pageLength),
				'CurrentBool' => $this->CurrentPage() == ($i + 1)
			)));
		}

		return $result;
	}

	/**
	 * Returns a summarised pagination which limits the number of pages shown
	 * around the current page for visually balanced.
	 *
	 * Example: 25 pages total, currently on page 6, context of 4 pages
	 * [prev] [1] ... [4] [5] [[6]] [7] [8] ... [25] [next]
	 *
	 * Example template usage:
	 * <code>
	 * 	<% if MyPages.MoreThanOnePage %>
	 * 		<% if MyPages.NotFirstPage %>
	 * 			<a class="prev" href="$MyPages.PrevLink">Prev</a>
	 * 		<% end_if %>
	 * 		<% control MyPages.PaginationSummary(4) %>
	 * 			<% if CurrentBool %>
	 * 				$PageNum
	 * 			<% else %>
	 * 				<% if Link %>
	 * 					<a href="$Link">$PageNum</a>
	 * 				<% else %>
	 * 					...
	 * 				<% end_if %>
	 * 			<% end_if %>
	 * 			<% end_control %>
	 * 		<% if MyPages.NotLastPage %>
	 * 			<a class="next" href="$MyPages.NextLink">Next</a>
	 * 		<% end_if %>
	 * 	<% end_if %>
	 * </code>
	 *
	 * @param  int $context The number of pages to display around the current
	 *         page. The number should be event, as half the number of each pages
	 *         are displayed on either side of the current one.
	 * @return SS_List
	 */
	public function PaginationSummary($context = 4) {
		$result  = new ArrayList();
		$current = $this->CurrentPage();
		$total   = $this->TotalPages();

		// Make the number even for offset calculations.
		if ($context % 2) {
			$context--;
		}

		// If the first or last page is current, then show all context on one
		// side of it - otherwise show half on both sides.
		if ($current == 1 || $current == $total) {
			$offset = $context;
		} else {
			$offset = floor($context / 2);
		}

		$left  = max($current - $offset, 1);
		$range = range($current - $offset, $current + $offset);

		if ($left + $context > $total) {
			$left = $total - $context;
		}

		for ($i = 0; $i < $total; $i++) {
			$link    = HTTP::setGetVar($this->getVar, $i * $this->pageLength);
			$num     = $i + 1;

			$emptyRange = $num != 1 && $num != $total && (
				$num == $left - 1 || $num == $left + $context + 1
			);

			if ($emptyRange) {
				$result->push(new ArrayData(array(
					'PageNum'     => null,
					'Link'        => null,
					'CurrentBool' => false
				)));
			} elseif ($num == 1 || $num == $total || in_array($num, $range)) {
				$result->push(new ArrayData(array(
					'PageNum'     => $num,
					'Link'        => $link,
					'CurrentBool' => $current == $num
				)));
			}
		}

		return $result;
	}

	/**
	 * @return int
	 */
	public function CurrentPage() {
		return floor($this->getPageStart() / $this->pageLength) + 1;
	}

	/**
	 * @return int
	 */
	public function TotalPages() {
		return ceil($this->getTotalItems() / $this->pageLength);
	}

	/**
	 * @return bool
	 */
	public function MoreThanOnePage() {
		return $this->TotalPages() > 1;
	}

	/**
	 * @return bool
	 */
	public function NotFirstPage() {
		return $this->CurrentPage() != 1;
	}

	/**
	 * @return bool
	 */
	public function NotLastPage() {
		return $this->CurrentPage() != $this->TotalPages();
	}

	/**
	 * Returns the number of the first item being displayed on the current
	 * page. This is useful for things like "displaying 10-20".
	 *
	 * @return int
	 */
	public function FirstItem() {
		return ($start = $this->getPageStart()) ? $start + 1 : 1;
	}

	/**
	 * Returns the number of the last item being displayed on this page.
	 *
	 * @return int
	 */
	public function LastItem() {
		if ($start = $this->getPageStart()) {
			return min($start + $this->pageLength, $this->getTotalItems());
		} else {
			return min($this->pageLength, $this->getTotalItems());
		}
	}

	/**
	 * Returns a link to the first page.
	 *
	 * @return string
	 */
	public function FirstLink() {
		return HTTP::setGetVar($this->getVar, 0);
	}

	/**
	 * Returns a link to the last page.
	 *
	 * @return string
	 */
	public function LastLink() {
		return HTTP::setGetVar($this->getVar, ($this->TotalPages() - 1) * $this->pageLength);
	}

	/**
	 * Returns a link to the next page, if there is another page after the
	 * current one.
	 *
	 * @return string
	 */
	public function NextLink() {
		if ($this->NotLastPage()) {
			return HTTP::setGetVar($this->getVar, $this->getPageStart() + $this->pageLength);
		}
	}

	/**
	 * Returns a link to the previous page, if the first page is not currently
	 * active.
	 *
	 * @return string
	 */
	public function PrevLink() {
		if ($this->NotFirstPage()) {
			return HTTP::setGetVar($this->getVar, $this->getPageStart() - $this->pageLength);
		}
	}

	// DEPRECATED --------------------------------------------------------------

	/**
	 * @deprecated 3.0 Use individual getter methods.
	 */
	public function getPageLimits() {
		Deprecation::notice('3.0', 'Use getPageStart, getPageLength, or getTotalItems instead.');
		return array(
			'pageStart'  => $this->getPageStart(),
			'pageLength' => $this->pageLength,
			'totalSize'  => $this->getTotalItems(),
		);
	}

	/**
	 * @deprecated 3.0 Use individual setter methods.
	 */
	public function setPageLimits($pageStart, $pageLength, $totalSize) {
		Deprecation::notice('3.0', 'Use setPageStart, setPageLength, or setTotalItems instead.');
		$this->setPageStart($pageStart);
		$this->setPageLength($pageLength);
		$this->setTotalSize($totalSize);
	}

}
